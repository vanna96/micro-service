import os
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import call, patch

import autoscale as scaler


def replica(number, address=None):
    return {
        "Id": f"replica-{number}",
        "Name": f"/backend-app-{number}",
        "Config": {"Labels": {"com.docker.compose.container-number": str(number)}},
        "State": {"Running": True, "Health": {"Status": "healthy"}},
        "NetworkSettings": {"Networks": {"app-network": {"IPAddress": address or f"172.18.0.{number + 10}"}}},
    }


class AutoscaleTests(unittest.TestCase):
    def policy(self):
        with patch.dict(os.environ, {}, clear=True), patch.object(scaler.time, "monotonic", return_value=0):
            return scaler.Policy()

    def test_sustained_high_cpu_starts_second_instance(self):
        policy = self.policy()
        for now in (0, 15, 30, 45):
            self.assertIsNone(policy.decision(1, 80, now))
        self.assertEqual(policy.decision(1, 80, 60), 2)

    def test_cpu_spike_does_not_scale(self):
        policy = self.policy()
        for now, cpu in ((0, 90), (15, 10), (30, 90), (45, 90), (60, 90)):
            self.assertIsNone(policy.decision(1, cpu, now))

    def test_status_uses_controller_cpu_units_and_reports_scale_timer(self):
        policy = self.policy()
        self.assertIsNone(policy.decision(1, 82.5, 0))
        with patch.object(scaler.time, "time", return_value=1000):
            status = policy.status(1, 82.5, 15)
        self.assertEqual(status["state"], "waiting_scale_up")
        self.assertEqual(status["cpu_percent"], 82.5)
        self.assertEqual(status["replicas"], 1)
        self.assertEqual(status["scale_up_cpu_percent"], 70)
        self.assertEqual(status["seconds_until_action"], 45)
        self.assertEqual(status["updated_at"], 1000)

    def test_status_file_is_replaced_atomically(self):
        policy = self.policy()
        with tempfile.TemporaryDirectory() as directory, \
                patch.object(scaler, "STATUS", Path(directory) / "status.json"), \
                patch.object(scaler.time, "time", return_value=1000):
            scaler.write_status(policy, 1, 12.34, 0)
            payload = json.loads(scaler.STATUS.read_text())
            self.assertEqual(payload["state"], "holding")
            self.assertEqual(payload["cpu_percent"], 12.34)
            self.assertFalse(scaler.STATUS.with_suffix(".tmp").exists())

    def test_low_combined_load_requires_five_minutes_and_cooldown(self):
        policy = self.policy()
        policy.last_change = 100
        for now in range(0, 391, 15):
            self.assertIsNone(policy.decision(2, 20, now))
        self.assertEqual(policy.decision(2, 20, 405), 1)
        # Two instances at 20% each cannot shrink under a 25% total threshold.
        self.assertIsNone(policy.decision(2, 40, 420))

    def test_failed_metrics_do_not_count_as_sustained_load(self):
        policy = self.policy()
        self.assertIsNone(policy.decision(1, 90, 0))
        self.assertIsNone(policy.decision(1, 90, 100))

    def test_upstream_validation_failure_restores_previous_file(self):
        primary, secondary = replica(1), replica(2)
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "laravel-upstream.conf"
            path.write_text(scaler.render([primary, secondary]))
            with patch.object(scaler, "UPSTREAM", path), patch.object(scaler, "workers", return_value={"10"}), \
                    patch.object(scaler, "docker", side_effect=[RuntimeError("invalid config"), "", ""]):
                with self.assertRaises(RuntimeError):
                    scaler.route([primary])
            self.assertEqual(path.read_text(), scaler.render([primary, secondary]))

    def test_down_waits_for_old_workers_before_stopping(self):
        primary, secondary = replica(1), replica(2)
        with patch.object(scaler, "replicas", return_value=[primary, secondary]), \
                patch.object(scaler, "workers", side_effect=[{"10"}, {"10", "20"}, {"20"}]), \
                patch.object(scaler, "route", return_value={"10"}), \
                patch.object(scaler, "state", return_value={"Running": True, "Health": {"Status": "healthy"}}), \
                patch.object(scaler, "heartbeat"), patch.object(scaler.time, "sleep") as sleep, \
                patch.object(scaler, "docker") as docker:
            self.assertTrue(scaler.scale_down())
            sleep.assert_called_once_with(2)
            self.assertEqual(docker.call_args_list, [
                call("stop", "--signal", "SIGQUIT", "--timeout", "30", secondary["Id"]),
                call("rm", secondary["Id"]),
            ])

    def test_primary_failure_never_stops_secondary(self):
        containers = [replica(1), replica(2)]
        with patch.object(scaler, "replicas", return_value=containers), \
                patch.object(scaler, "workers", return_value={"10"}), \
                patch.object(scaler, "route", return_value={"10"}) as route, \
                patch.object(scaler, "state", return_value={"Running": False}), \
                patch.object(scaler, "heartbeat"), patch.object(scaler, "docker") as docker:
            with self.assertRaises(RuntimeError):
                scaler.scale_down()
            route.assert_any_call(containers)
            docker.assert_not_called()

    def test_scale_up_uses_compose_and_waits_for_health(self):
        containers = [replica(1), replica(2)]
        with patch.object(scaler, "replicas", return_value=[containers[0]]), \
                patch.object(scaler, "compose_scale") as scale, \
                patch.object(scaler, "wait_replicas", return_value=containers) as wait, \
                patch.object(scaler, "route") as route:
            scaler.scale_up()
            scale.assert_called_once_with(2)
            wait.assert_called_once_with(2)
            route.assert_called_once_with(containers)

    def test_compose_uses_host_checkout_and_preserves_primary(self):
        with patch.object(scaler, "context", return_value=("custom-project", "/host/checkout/backend")), \
                patch.object(scaler, "docker") as docker:
            scaler.compose_scale(2)
            args = docker.call_args.args
            self.assertEqual(args[args.index("--project-directory") + 1], "/host/checkout/backend")
            self.assertEqual(args[args.index("--project-name") + 1], "custom-project")
            self.assertIn("--no-recreate", args)
            self.assertIn("--no-deps", args)
            self.assertEqual(args[-2:], ("app=2", "app"))
            with self.assertRaises(ValueError):
                scaler.compose_scale(3)

    def test_discovery_sorts_replicas_and_refuses_more_than_two(self):
        containers = [replica(2), replica(1)]
        with patch.object(scaler, "context", return_value=("backend", "/host/backend")), \
                patch.object(scaler, "docker", side_effect=["second\nfirst", json.dumps(containers)]):
            self.assertEqual([value["Id"] for value in scaler.replicas()], ["replica-1", "replica-2"])
        with patch.object(scaler, "context", return_value=("backend", "/host/backend")), \
                patch.object(scaler, "docker", side_effect=["first\nsecond\nthird", json.dumps(containers + [replica(3)])]):
            with self.assertRaises(RuntimeError):
                scaler.replicas()

    def test_render_updates_when_container_address_changes(self):
        before = scaler.render([replica(1)])
        after = scaler.render([replica(1, "172.18.0.99")])
        self.assertNotEqual(before, after)
        self.assertIn("server 172.18.0.99:9000;", after)
        with self.assertRaises(ValueError):
            scaler.render([])


if __name__ == "__main__":
    unittest.main()

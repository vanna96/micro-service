export function TenantHostRedirect() {
  const script = `
    (function () {
      var hostname = window.location.hostname;
      if (hostname !== "localhost" && hostname !== "127.0.0.1") return;
      fetch("/v1/api/tenant-host", { cache: "no-store" })
        .then(function (response) { return response.json(); })
        .then(function (payload) {
          var domain = payload && payload.success && payload.data && payload.data.domain;
          if (!domain) return;
          var port = window.location.port ? ":" + window.location.port : "";
          window.location.replace("http://" + domain + port + "/");
        })
        .catch(function () {});
    })();
  `;

  return <script dangerouslySetInnerHTML={{ __html: script }} />;
}

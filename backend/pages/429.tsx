import StatusPage from "@/components/status-page";

export default function TooManyRequestsPage() {
  return <StatusPage statusCode={429} />;
}

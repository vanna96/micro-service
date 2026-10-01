import * as Sentry from "@sentry/nextjs";
import type { NextPageContext } from "next";
import Error, { type ErrorProps } from "next/error";
import StatusPage from "@/components/status-page";

type CustomErrorProps = ErrorProps & {
  hasGetInitialPropsRun?: boolean;
  err?: Error;
};

const CustomErrorComponent = (props: CustomErrorProps) => {
  return <StatusPage statusCode={props.statusCode || 500} />;
};

CustomErrorComponent.getInitialProps = async (contextData: NextPageContext) => {
  await Sentry.captureUnderscoreErrorException(contextData);
  return Error.getInitialProps(contextData);
};

export default CustomErrorComponent;

import * as Sentry from "@sentry/nextjs";
import type { NextPageContext } from "next";
import Error, { type ErrorProps } from "next/error";

type CustomErrorProps = ErrorProps & {
  hasGetInitialPropsRun?: boolean;
  err?: Error;
};

const CustomErrorComponent = (props: CustomErrorProps) => {
  return <Error statusCode={props.statusCode} />;
};

CustomErrorComponent.getInitialProps = async (contextData: NextPageContext) => {
  await Sentry.captureUnderscoreErrorException(contextData);
  return Error.getInitialProps(contextData);
};

export default CustomErrorComponent;

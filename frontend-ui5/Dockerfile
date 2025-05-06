# Use official Node.js v20.16.0 image
FROM node:20.16.0-alpine

# Set working directory
WORKDIR /usr/src/app

# Install UI5 CLI globally
RUN npm install -g @ui5/cli && \
    npm install --save-dev @eslint/js

# Copy package files
COPY package*.json ./

# Install dependencies (optional)
RUN npm install

# Copy the rest of the project
COPY . .

# Expose the UI5 server port
EXPOSE 8080

# Start the UI5 development server
CMD ["ui5", "serve", "--accept-remote-connections"]

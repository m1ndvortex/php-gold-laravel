FROM node:18-alpine

WORKDIR /var/www

# Install dependencies
RUN apk add --no-cache git

# Copy package files
COPY package*.json ./

# Install npm dependencies
RUN npm install

# Copy source code
COPY . .

# Expose Vite dev server port
EXPOSE 5173

# Start Vite dev server with host binding for Docker
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
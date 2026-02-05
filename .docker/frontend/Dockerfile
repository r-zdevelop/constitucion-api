FROM node:22-alpine

# Set working directory
WORKDIR /app

# Install Angular CLI globally
RUN npm install -g @angular/cli

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm install

# Copy application files
COPY . .

# Expose port 4200
EXPOSE 4200

# Start Angular development server with host binding
CMD ["ng", "serve", "--host", "0.0.0.0", "--poll", "2000"]

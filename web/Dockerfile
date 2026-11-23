# Development image for the React client (Vite dev server).
# Production builds static files (`npm run build`) for S3 + CloudFront; see DECISIONS.md.
FROM node:22-alpine

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

COPY . .
EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]

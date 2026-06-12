FROM node:20-alpine
WORKDIR /app
COPY package.json ./
RUN npm install --production --ignore-scripts
COPY server.js ./
# Run as non-root
RUN addgroup -S mcp && adduser -S mcp -G mcp
USER mcp
EXPOSE 6300
CMD ["node", "server.js"]

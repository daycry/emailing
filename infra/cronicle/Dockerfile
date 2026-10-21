FROM node:22-bookworm-slim@sha256:3ed98443660ff48413b5af19ecefce3b9d50845a901a43420d47cc63a9fddbe9

RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates curl procps tzdata \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /opt/cronicle
# Original upstream release; this is a local image, not an upstream Docker image.
RUN curl --fail --location --retry 3 https://github.com/jhuckaby/Cronicle/archive/refs/tags/v0.9.137.tar.gz -o /tmp/cronicle.tar.gz \
    && tar -xzf /tmp/cronicle.tar.gz --strip-components=1 -C /opt/cronicle \
    && rm /tmp/cronicle.tar.gz \
    && npm install --omit=dev --ignore-scripts --no-audit --no-fund \
    && node bin/build.js dist \
    && test -f conf/config.json \
    && mkdir -p data logs queue \
    && chown -R node:node /opt/cronicle
COPY --chown=node:node bootstrap.js /opt/cronicle/lab-bootstrap.js
COPY --chown=node:node health.js /opt/cronicle/lab-health.js
USER node
EXPOSE 3012
CMD ["sh", "-c", "node lab-bootstrap.js && exec node lib/main.js --foreground"]

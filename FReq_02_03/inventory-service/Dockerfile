FROM golang:1.22-alpine AS build
WORKDIR /src
COPY go.mod go.sum* ./
RUN go mod download
COPY . .
RUN go build -trimpath -ldflags='-s -w' -o /inventory-service ./cmd/server

FROM alpine:3.20
RUN apk add --no-cache ca-certificates netcat-openbsd
COPY --from=build /inventory-service /usr/local/bin/inventory-service
EXPOSE 50051
ENTRYPOINT ["/usr/local/bin/inventory-service"]

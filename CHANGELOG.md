# Changelog

All notable changes to the LaraRabbit package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-05-13

### Added
- Initial release of the LaraRabbit package for Laravel
- RabbitMQ connection management with automatic reconnection and exponential backoff
- Consumer implementation with robust error handling
- Publisher implementation for reliable message delivery
- Support for topic, direct, and fanout exchange types
- Multiple serialization formats (JSON, MessagePack)
- Dead letter queue support for failed message handling
- Circuit breaker pattern implementation to prevent cascading failures
- Retry mechanisms with configurable attempts and delays
- Message validation against JSON schemas
- Comprehensive telemetry and performance monitoring
- Detailed error logging with context information
- Laravel Facade for easy integration with Laravel applications
- Auto-discovery of the service provider
- Predefined queues support with centralized configuration
- Event publishing with automatic metadata generation
- Batch processing with detailed progress tracking and statistics

### Features
- Automatic queue setup and binding with exchange
- Support for publishing individual messages and batches
- Configurable prefetch count for consumers
- Auto-acknowledgement options for simplifying message processing
- Delivery tag caching for improved reliability
- Graceful handling of connection failures
- Message correlation for distributed tracing
- Header-based message routing
- Configurable SSL/TLS connections
- Support for queue arguments (e.g., message TTL, max length)
- Comprehensive configuration options via environment variables
- Unit and integration tests for all components

### Documentation
- Full documentation available in the `docs` directory
- Error handling guide in `docs/ERROR_HANDLING.md`
- Examples of common usage patterns
- Configuration reference guide
- Predefined queues configuration guide
- Event publishing documentation
- Batch processing documentation
- Troubleshooting guide

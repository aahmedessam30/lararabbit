# Troubleshooting LaraRabbit

This guide provides solutions to common issues you might encounter when using LaraRabbit.

## Connection Issues

### Unable to Connect to RabbitMQ

**Problem**: `AMQPConnectionException: Connection refused`

**Solutions**:
1. Verify RabbitMQ is running: `rabbitmqctl status`
2. Check connection details in your `.env` file:
   ```
   RABBITMQ_HOST=localhost
   RABBITMQ_PORT=5672
   RABBITMQ_USER=guest
   RABBITMQ_PASSWORD=guest
   RABBITMQ_VHOST=/
   ```
3. Ensure firewall settings allow connections to the RabbitMQ port
4. Check network connectivity: `telnet [host] [port]`

## Consuming Issues

### Messages Not Being Consumed

**Problem**: Queue has messages but consumer doesn't receive them

**Solutions**:
1. Verify binding keys match between publisher and consumer
2. Check queue bindings: `php artisan lararabbit:list-bindings`
3. Ensure consumer has proper permissions on the vhost
4. Verify prefetch count settings aren't too low

### High CPU Usage During Consumption

**Problem**: Consumer process uses high CPU

**Solutions**:
1. Increase the `wait_timeout` in your configuration:
   ```php
   'consumer' => [
       'wait_timeout' => 3, // Seconds
   ]
   ```
2. Ensure your message processing logic is efficient

## Publishing Issues

### Failed to Publish Messages

**Problem**: `CircuitBreakerOpenException` or publishing failures

**Solutions**:
1. Check RabbitMQ connection and status
2. Verify exchange exists and has correct type
3. Examine circuit breaker settings:
   ```php
   'resilience' => [
       'failure_threshold' => 5,
       'reset_timeout' => 30,
   ]
   ```
4. Look for exceptions in your Laravel logs

### Serialization Errors

**Problem**: `SerializationException` when publishing messages

**Solutions**:
1. Ensure data is serializable (no resources, closures, etc.)
2. Try alternate serialization format: `RabbitMQ::setSerializationFormat('json')`
3. Make sure the MessagePack extension is installed if using msgpack format

## Configuration Issues

### Queue Not Being Created

**Problem**: Queue doesn't appear in RabbitMQ management console

**Solutions**:
1. Call `setupQueue` explicitly before consumption
2. Verify permissions allow queue creation
3. Use `rabbitmqctl list_queues` to check if queue exists but isn't visible

### Predefined Queue Not Found

**Problem**: `InvalidArgumentException: Queue configuration not found`

**Solutions**:
1. Ensure you've published the queues config file: `php artisan vendor:publish --tag=lararabbit-queues-config`
2. Check that the queue key exists in the `config/rabbitmq-queues.php` file
3. Verify you're using the correct key name in your code

## Dead Letter Queues

### Failed Messages Not Going to DLQ

**Problem**: Failed messages aren't appearing in the dead letter queue

**Solutions**:
1. Make sure you've set up the dead letter queue correctly:
   ```php
   RabbitMQ::setupDeadLetterQueue('source_queue', 'failed_queue', ['failed.routing.key']);
   ```
2. Ensure you're rejecting messages without requeuing: `RabbitMQ::reject($message, false)`
3. Check that the dead letter exchange is properly bound to the dead letter queue

## Still Having Issues?

If you're still encountering problems, try:

1. Enabling debug mode:
   ```
   RABBITMQ_DEBUG=true
   ```

2. Check Laravel logs for detailed error information

3. Open an issue on the [GitHub repository](https://github.com/aahmedessam30/lararabbit/issues) with:
   - LaraRabbit version
   - RabbitMQ version
   - Laravel version
   - Full error message
   - Steps to reproduce
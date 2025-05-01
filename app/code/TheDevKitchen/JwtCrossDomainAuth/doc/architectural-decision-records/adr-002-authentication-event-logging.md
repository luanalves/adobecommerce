/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 *
 * Este arquivo segue as convenções de documentação definidas pela PSR-5 (PHPDoc Standard)
 * para identificação de autoria, copyright e licenciamento.
 */

# ADR-002: Authentication Event Logging with RabbitMQ

## Status
Accepted

## Date
2025-04-30

## Context
Following our implementation of JWT-based cross-domain authentication (see ADR-001), we need a robust way to track and audit authentication events across domains. The current system generates JWT tokens for cross-domain authentication, but lacks comprehensive logging and monitoring capabilities to:

1. Provide a complete audit trail of cross-domain authentication events
2. Enable security analytics and anomaly detection
3. Ensure compliance with security requirements and regulations
4. Support troubleshooting of authentication issues across domains
5. Create alerts for suspicious authentication patterns

Since our system already uses RabbitMQ for asynchronous processing of authentication events, we need to define a standardized approach for logging these events.

## Decision Drivers
* Security and audit requirements
* Performance impact on authentication process
* Scalability for high-volume authentication events
* Operational visibility and troubleshooting capabilities
* Compliance with data protection regulations

## Considered Options
1. **Direct database logging**: Log all authentication events directly to the database from each domain.
2. **File-based logging**: Write authentication events to log files on each domain.
3. **Third-party logging service**: Integrate with an external logging service like Splunk or ELK stack.
4. **RabbitMQ centralized logging**: Use our existing RabbitMQ integration to centralize authentication events.
5. **Hybrid approach**: Use RabbitMQ for immediate events and batch processing for detailed logs.

## Decision
We will implement **Option 4: RabbitMQ centralized logging** for authentication events.

### Documentation and Coding Standards
All implementation will adhere to the following PHP Standards Recommendations:
- **PSR-5**: For documentation blocks and comments in code (PHPDoc Standard)
- **PSR-12**: For coding style and formatting
- **PSR-3**: For logging interfaces and implementation
- **PSR-4**: For autoloading and class namespace organization

These standards ensure code maintainability, readability, and proper documentation across the module.

### Authentication Logging Flow
The authentication logging flow will work as follows:

1. When a cross-domain authentication event occurs (token generation or validation), minimal information is logged locally for immediate diagnostics.
2. A detailed authentication event message is published to a dedicated RabbitMQ exchange `crossdomain.auth.events`.
3. Multiple specialized consumers process these events:
   - A logging consumer writes events to a centralized database for audit and reporting
   - A monitoring consumer analyzes patterns for security alerts
   - An analytics consumer aggregates statistics for dashboards

### Event Structure
All authentication events will follow this standardized JSON format:
```json
{
  "event_id": "uuid-v4-identifier",
  "event_type": "auth.token.generated | auth.token.validated | auth.token.rejected",
  "timestamp": "ISO-8601-timestamp",
  "source_domain": "domain-a.com",
  "target_domain": "domain-b.com",
  "user_info": {
    "customer_id": "customer-identifier",
    "is_logged_in": true,
    "customer_group": "group-name",
    "ip_address": "anonymized-ip"
  },
  "request_metadata": {
    "user_agent": "browser-info",
    "referer": "request-source-url"
  },
  "security_metadata": {
    "token_id": "jti-from-jwt",
    "token_exp": "token-expiration-time",
    "validation_status": "success | error.reason"
  }
}
```

### Retention Policy
- Real-time events: 7 days in RabbitMQ (with TTL)
- Processed events: 90 days in detailed form
- Aggregated statistics: 2 years
- Security incidents: 7 years (with appropriate anonymization)

### Security Considerations
- PII (Personally Identifiable Information) will be minimized according to GDPR principles
- IP addresses will be partially anonymized
- Log access will be restricted and audited
- Sensitive fields will be encrypted at rest

## Consequences

### Advantages
- Decoupled logging doesn't impact authentication performance
- Centralized visibility across all domains
- Flexible processing for different use cases (security, analytics, auditing)
- Scalable architecture for high traffic volumes
- Consistent event format facilitates analysis

### Disadvantages
- Increased complexity in the logging system
- Dependency on RabbitMQ availability
- Need for additional consumers and processors
- Requires monitoring of the message queue itself

### Risks and Mitigations
- **Risk**: Message queue overload during traffic spikes
  **Mitigation**: Implement throttling and circuit breakers

- **Risk**: Loss of events if consumers fail
  **Mitigation**: Dead-letter queues, retry policies, and monitoring

- **Risk**: Privacy compliance issues
  **Mitigation**: Data minimization, anonymization, and retention policies

## Related Decisions
- Builds on ADR-001: JWT-based Cross-Domain Authentication
- Integration with existing Magento logging mechanisms
- Potential for future expansion to general security event monitoring

## References
- [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html)
- [RabbitMQ Best Practices](https://www.cloudamqp.com/blog/part1-rabbitmq-best-practice.html)
- [GDPR Compliance for Log Data](https://www.privacypolicies.com/blog/gdpr-compliance-log-files/)
- [PSR-5: PHPDoc Standard (Draft)](https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md)
- [PSR-3: Logger Interface](https://www.php-fig.org/psr/psr-3/)
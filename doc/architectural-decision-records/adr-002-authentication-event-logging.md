/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 *
 * Este arquivo segue as convenções de documentação definidas pela PSR-5 (PHPDoc Standard)
 * para identificação de autoria, copyright e licenciamento.
 */

# ADR-002: Authentication Event Logging

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
4. **Hybrid approach**: Combine immediate file logging with periodic database aggregation.

## Decision
We will implement **Option 1: Direct database logging** for authentication events.

### Documentation and Coding Standards
All implementation will adhere to the following PHP Standards Recommendations:
- **PSR-5**: For documentation blocks and comments in code (PHPDoc Standard)
- **PSR-12**: For coding style and formatting
- **PSR-3**: For logging interfaces and implementation
- **PSR-4**: For autoloading and class namespace organization

These standards ensure code maintainability, readability, and proper documentation across the module.

### Authentication Logging Flow
The authentication logging flow will work as follows:

1. When a cross-domain authentication event occurs (token generation or validation), the event details are immediately logged to the database.
2. A background cleanup process manages log retention according to defined policies.
3. Periodic analysis jobs process the logs for:
   - Security monitoring and alerting
   - Usage analytics and reporting
   - Audit compliance checks

### Event Structure
All authentication events will follow this standardized format in the database:
```
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
- Detailed events: 90 days
- Aggregated statistics: 2 years
- Security incidents: 7 years (with appropriate anonymization)

### Security Considerations
- PII (Personally Identifiable Information) will be minimized according to GDPR principles
- IP addresses will be partially anonymized
- Log access will be restricted and audited
- Sensitive fields will be encrypted at rest
- Database partitioning for improved query performance

## Consequences

### Advantages
- Direct logging provides immediate visibility
- Simple architecture with fewer moving parts
- Built-in Magento database reliability
- Easy integration with existing backup procedures
- Consistent event format facilitates analysis

### Disadvantages
- Potential performance impact during high traffic
- Additional database storage requirements
- Need for regular maintenance and cleanup
- Database backup size increase

### Risks and Mitigations
- **Risk**: Database performance impact during traffic spikes
  **Mitigation**: Implement efficient indexing and partitioning

- **Risk**: Storage growth
  **Mitigation**: Regular cleanup jobs and data archiving

- **Risk**: Privacy compliance issues
  **Mitigation**: Data minimization, anonymization, and retention policies

## Related Decisions
- Builds on ADR-001: JWT-based Cross-Domain Authentication
- Integration with existing Magento logging mechanisms
- Potential for future expansion to general security event monitoring

## References
- [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html)
- [GDPR Compliance for Log Data](https://www.privacypolicies.com/blog/gdpr-compliance-log-files/)
- [PSR-5: PHPDoc Standard (Draft)](https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md)
- [PSR-3: Logger Interface](https://www.php-fig.org/psr/psr-3/)
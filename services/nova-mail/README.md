# Nova notification delivery

Install `email.php` as Nova's private `application/config/email.php` on the current HelioHost installation. It uses the local SMTP relay at `127.0.0.1:25`; the hosting PHP runtime disables `proc_open`, so SwiftMailer's Sendmail transport cannot run there. Local SMTP avoids shell execution and needs no mailbox password. This configuration is specific to this host's local relay, not a public SMTP endpoint.

Nova's `system_email` setting must be on, with `default_email_address` set to `nova@septenture.helioho.st`. Publish the sender's SPF and DKIM records before activation. Notification preferences are initialized by the SSO bridge; explicit player opt-outs remain respected. Shared-draft save notifications go to the story's coauthors with that preference enabled. Keyword tags are catalogue metadata and do not create @mention notifications.

Production credentials, delivery-test addresses, test messages, and DNS exports do not belong in this repository.

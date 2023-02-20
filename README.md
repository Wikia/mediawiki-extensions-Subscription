# Extension:Subscription

The Subscription extension can consume internal and external subscription services to enable premium functionality for users on a wiki.

## Developers
### How do I check if an user has an active subscription?

Use `Subscription::hasSubscription( $userId )`

### How do I implement my own subscription provider?

Extend the abstract class \Subscription\SubscriptionProvider.  The class has documentation on the functions to override for basic functionality.

Then add the class to `Subscription` class constructor.

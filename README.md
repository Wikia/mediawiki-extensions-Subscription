# Extension:Subscription

The Subscription extension can consume internal and external subscription services to enable premium functionality for users on a wiki.

## Developers
### How do I check if an user has an active subscription?

If the wiki is using a Central ID provider such as CentrlAuth or HydraAuth then construct a Subscription object from an existing valid User object.  If the user is not attached to a global account then `newFromUser()` will return false.
```php
$subscription = \Subscription\Subscription::newFromUser($user);
```

Otherwise, the Subscription can be constructed manually with a known user ID relevant to the Subscription service being used.
```php
$userId = $example->getOtherServiceUserId();
$subscription = new \Subscription\Subscription($userId);
```

Finally, call `hasSubscription()` on the object which return a boolean.

Usage Example:
```php
$activeSubscription = false;
$subscription = \Subscription\Subscription::newFromUser($user);
if ($subscription !== false && $subscription->hasSubscription()) {
	$activeSubscription = true;
}
```

### How do I implement my own subscription provider?

Extend the abstract class \Subscription\SubscriptionProvider.  The class has documentation on the functions to override for basic functionality.

Then add the class to $wgSubscriptionProviders.

```php
$wgSubscriptionProviders["CursePremium"] = ["class" => "Subscription\\SubscriptionProvider\\ExampleSubscription"];
```

# Controller Policy

This module has been designed to provide the ability to configure response policies that apply per specific
Controller-derived class.

### Example: simple policy

Let's say we want to apply a caching header of max-age 300 to the HomePage only. This module comes with a
`CachingPolicy` which by implementing the `ControllerPolicy` interface can be applied to anything derived from
`Controller`. This class can also be configured to specify the custom max-age via (injected) properties.

Using this policy is done via your project-specific **config.yml**. We configure the pseudo-singleton via
Dependency Injection and apply it directly to `HomePage_Controller`:

	Injector:
	  MyCachingPolicy:
		class: CachingPolicy
		properties:
		  cacheAge: 300
		  vary: 'Cookie, X-Forwarded-Protocol, Accept'
	HomePage_Controller:
	  dependencies:
		Policies: '%$MyCachingPolicy'

Every policy will set headers on top of the default framework's `HTTP::add_cache_headers`, which is exactly what we
want. This allows us to for example customise the `Vary` headers per policy, which were previously hardcoded.

Note: the policies will be applied in the Controller order of initialisation, so if multiple Controllers are invoked the
latter will override the former. HOWEVER this is very unlikely and has nothing to do with the inheritance of classes
(see next example).  This relates to how the Controller stack is invoked in SilverStripe. The extension point in
`ControllerPolicyApplicator` has been chosen such that the `ModelAsController` and `RootURLController` do not trigger
application of policies, and it is expected that only one controller will trigger the policy.

### Example: complex policies

This example illustrates the usage of array-merging capability of the config system, which will enable you to simulate
policy inheritance that will reflect your class diagram.

In this example we want to configure a global setting consisting of two policies, one setting the max-age to 300, and
second to configure custom header. Then we want to add more specific policy for the home page max-age, while keeping the
custom header. Here is how to achieve this using the config system:

	Injector:
	  ShortCachingPolicy:
		class: CachingPolicy
		properties:
		  cacheAge: 300
	  LongCachingPolicy:
		class: CachingPolicy
		properties:
		  cacheAge: 3600
	  CustomPolicy:
		 class: CustomHeaderPolicy
		 properties:
		   headers:
			 Custom-Header: "Hello"
	HomePage_Controller:
	  dependencies:
		Policies:
		  - '%$LongCachingPolicy'
	Controller:
	  dependencies:
		Policies:
		  - '%$ShortCachingPolicy'
		  - '%$CustomPolicy'

Outcome of the array merging for the home page will be as follows:

 * LongCachingPolicy
 * ShortCachingPolicy
 * CustomPolicy

We handle this array in reverse order, meaning that by default the top policy (most specific Controller) will override
the others. This does not mean many Controller policies will trigger - rather, one Controller will apply a merged set.

Caution: you can either use the array syntax, or value syntax. Choose what's easier.

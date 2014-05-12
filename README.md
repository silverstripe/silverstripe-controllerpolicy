# Controller Policy

This module has been designed to provide the ability to configure response policies that apply per specific
Controller-derived class.

### Example: customising the Cache HTTP header

Let's say we want to apply a caching header of max-age 300 to the HomePage only. This module comes with a
`CachingPolicy` which by implementing the `ControllerPolicy` interface can be applied to anything derived from
`Controller`. This class can also be configured to specify the custom max-age via (injected) properties.

Using this policy is done via your project-specific **config.yml**. We configure the pseudo-singleton via
Dependency Injection (the "Injector" part below) and we apply it to the controller using the applicator object.

	Injector:
	  MyCachingPolicy:
		class: CachingPolicy
		properties:
		  cacheAge: 300
	HomePage_Controller:
	  dependencies:
		Policy: '%$MyCachingPolicy'
	  extensions:
		- ControllerPolicyApplicator

Note: this policy will override the default framework `HTTP::add_cache_headers`, which is exactly what we want. This
allows us to for example customise the `Vary` headers which were previously hardcoded.

Also, the policies will be applied in the order they are added, so if multiple Controllers are invoked the latter will
override the former. However this is unlikely: the extension point in `ControllerPolicyApplicator` has been chosen such
that the `ModelAsController` and `RootURLController` do not trigger application of policies.

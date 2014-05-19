# Controller Policy

This module has been designed to provide the ability to configure response policies that apply per specific
Controller.

It comes with a small selection of policies for implementing caching:

* CachingPolicy: rewrite of SilverStripe default.
* CustomHeaderPolicy: allow adding any headers via config system.
* NoopPolicy: allows zero-ing policies in Controllers extending other Controllers.

An example Page extension PageControlledPolicy is also provided utilising CachingPolicy's ability to customise
max-age based on CMS configuration on specific objects.

Note that at the moment the `preRequest` filters are not too useful because they don't allow us to short-circuit
the execution and allow us to for example return 304 early. We are working on a
[pull request](https://github.com/silverstripe/silverstripe-framework/pull/3130) to make it possible to return something
else than an `SS_HTTP_Exception` from this handler.

### Configuring

Here is a starter configuration you can use for your caching.

	---
	Name: caching
	Except:
	  environment: dev
	---
	ControllerPolicyRequestFilter:
	  ignoreDomainRegexes:
		- '/.*\.preview.server.com$/'

	[specific options go here - see examples that follow]

If you can, use a separate domain for your CMS. You will then be able to ignore it for caching via
`ignoreDomainRegexes`.  This will ensure your editors can still see the changes they have published immediately.

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
	Page_Controller:
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

### Example: PageControlledPolicy

Here is an example of how to implement CMS capability to override the max-age per specific page. In your config file
put the following statements:

	Injector:
	  GeneralCachingPolicy:
		class: CachingPolicy
		properties:
		  cacheAge: 900
	Page_Controller:
	  dependencies:
		Policies: '%$GeneralCachingPolicy'
	Page:
	  extensions:
		- PageControlledPolicy

Here, applying the `PageControlledPolicy` extension to the `Page` results in a new "MaxAge" field being written into the
DB, and a new tab available ("Caching") which lets the ADMIN user tweak the cache max-age header (denominated in
minutes).

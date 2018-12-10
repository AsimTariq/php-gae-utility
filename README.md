# php-gae-util
Utility belt for common tasks and patterns on Google App Engine for PHP.
The goal is to make development of microservices on Google App Engine
go fast and smooth. Handling common GAE scenarios with less code.

## Modules
* **Auth:** Handles several issues around getting users autenticated.
* **Cached:** Just a simple wrapper for memcache to make this code better.
* **Conf:** A wrapper around `hassankhan/config` which provides a lightweighhed
library for doing such stuff. This wrapper is adapted for GAE.
* **Fetch:** Simple module to ensure service to service communication.
* **JWT:** My module to handle all the work on JWT-tokens. Wrapper around
`firebase/php-jwt`
* **Secrets:** Module to handle keeping secrets secret. Using Google KMS
to secure passwords and tokens.
* **Workflow:** Module to handle the running of scheduled tasks on GAE
its main contribution is to store the state of the job running. Saves
State to DataStore.



### Login in to provide service account credentials in development

For local developent its important to get [Application Default Credentials]
if you want to use resources on the Google Cloud platform. You log in by
running this command:

```bash

$ gcloud auth application-default login

```

You need to restart the devserver to get this working as the credentials
get set on startup time.

## Testing and development

### Testing
Test-coverage is an important part of creating reusable, reliable code.
The goal of this testing is to use phpunit as this is the most used
PHP test framework. As several methods are dependent on Cloud Datastore installing
understanding and being able to test against the emulator is quite important.
The emulator is not the same as the built in emulator that is running inside the 
GAE devserver emulator.

#### Cloud datastore setup for testing

```bash
$ gcloud components install cloud-datastore-emulator
$ gcloud beta emulators datastore start
```


### For local development of packages

My strategy for developing packages for packagist is as following.

* Create a local folder where you symlink packages

In `~/composer/config.json` add, this also works with using the projects composer.json file, but
then you might get problems on other developers computers and in
pipelines.

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "~/path/to/liberary/root",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

This will now create a link. Two tricks for getting a more problem free
start of development is to add your liberary with "*" for and set minimum
stability in the local composer.json for the liberary that requires your
packages:

```json
{
  "minimum-stability": "dev",
  "require": {
    "mijohansen/php-gae-util": "*"
  }
}
```



### Coding style
The liberary consist of several separate Classes that really just form
a set of functions which should be fairly simple to introduce to code.
From version 0.7.0 every static method and function is written in
camelCase. And I try to follow [PSR-1] and [PSR-2]


[PSR-1]: https://www.php-fig.org/psr/psr-1/
[PSR-2]: https://www.php-fig.org/psr/psr-2/
[Application Default Credentials]: https://cloud.google.com/sdk/gcloud/reference/auth/application-default/login




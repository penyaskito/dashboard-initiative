| [![build status](https://github.com/penyaskito/dashboard-initiative/actions/workflows/ci.yml/badge.svg)](https://github.com/penyaskito/dashboard-initiative/actions/workflows/ci.yml) |
|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| [Online demo by Tugboat](https://main-ps44ayjkzq3gdy5zk1fifpraj8ctkihy.tugboatqa.com/)                                                                                 |


About the initiative
====

Create a new and modern Dashboard based on user needs, and define those needs per role.

The homepage of the initiative is at the Ideas issue queue. See ["Enhance user experience with customizable dashboards"](https://www.drupal.org/project/ideas/issues/3244581)
(was "Modern Dashboard with Role presets") on drupal.org, which provides a lot of context.

Development happens in a [sandbox project](https://www.drupal.org/sandbox/penyaskito/3327580). Feel free to create or work on issues in the sandbox. 

Setup
====

Clone this repository:

```
git clone git@github.com:penyaskito/dashboard-initiative.git
```
If you are planning to use ddev, now it's a good moment for ```ddev start```.

Then, you need to install all the dependencies using ```ddev composer install```.

You can run ```ddev exec ./install.sh```

For updating your env to the latest:

```
ddev composer update drupal/dashboard
```

You might need to run ```ddev exec ./install.sh``` again, as we are not providing
any upgrade paths yet.

For contributing:
====

Go to web/modules/contrib/dashboard and ensure to set the origin:

```
git remote set-url origin git@git.drupal.org:sandbox/penyaskito-3327580.git
```

Git checkout 1.0.x

```
git co 1.0.x
```

If you can, also create a PR here applying the patch via [composer.json](https://github.com/penyaskito/dashboard-initiative/blob/main/composer.json#L86). 
This helps with:
* Running the tests, as sandboxes don't have CI enabled.
* Get a tugboat link with a site with the patch applied where people can test the fix/improvement.
* Giving appropiate credit here too for your contribution!

Running tests
====

Running all tests:

```
ddev exec phpunit --testsuite all
```


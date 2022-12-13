Expose: an IDS for PHP
=========================

Expose is an Intrusion Detection System for PHP loosely based on the PHPIDS project (and using its ruleset
for detecting potential threats).

**ALL CREDIT** for the rule set for Expose goes to the PHPIDS project. Expose literally
uses the same JSON configuration for its execution. I am not claiming any kind of ownership
or authorship of these rules. Please see [the PHPIDS github README](https://github.com/PHPIDS/PHPIDS)
for names of those who have contributed.

**NOTE:** An IDS system should not be relied upon for sole protection in your environment! It should only be used in
the first level of threat identification. Please read up on "[Defense in Depth](http://websec.io/2012/10/12/Core-Concepts-Defense-in-Depth.html)"
for more information on a layered security approach.

### Quick Install

1. Install Composer:

    ```
    curl -s https://getcomposer.org/installer | php
    ```

1. Require Expose as a dependency using Composer:

    ```
    php composer.phar require barracudanetworks/expose
    ```

1. Install Expose:

    ```
    php composer.phar install
    ```

### Example Usage

```php
<?php
require 'vendor/autoload.php';

$data = array(
    'POST' => array(
        'test' => 'foo',
        'bar' => array(
            'baz' => 'quux',
            'testing' => '<script>test</script>'
        )
    )
);

$filters = new \Expose\FilterCollection();
$filters->load();

//instantiate a PSR-3 compatible logger
$logger = new \Expose\Log\Mongo();

$manager = new \Expose\Manager($filters, $logger);
$manager->run($data);

echo 'impact: '.$manager->getImpact()."\n"; // should return 8

// get all matching filter reports
$reports = $manager->getReports();
print_r($reports);

// export out the report in the given format ("text" is default)
echo $manager->export();
echo "\n\n";

```

### Parent Project Documentation

Parent GitHub
[https://github.com/enygma/expose]

Full (current) documentation for Expose can be found here: [ReadTheDocs for Expose](https://expose.readthedocs.org/en/latest/)

If you're curious as to the importance of application-level intrusion detection, check out [this article](https://www.owasp.org/index.php/ApplicationLayerIntrustionDetection)
on the OWASP site.

Feel free to contact me with questions or how you can help the project!

@author Chris Cornutt <ccornutt@phpdeveloper.org>

### Reason For Fork

The above project has not been maintained and is no longer compatible with current versions of PHP.

Currently supported versions:
- PHP 7.4
- PHP 8.0
- PHP 8.1
- PHP 8.2

### Latest Changes

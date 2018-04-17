# Usage

`composer install`

```
<?php

include "./vendor/autoload.php";
$file = 'raw-message-of-facebook.html';

$parser = new \w\MessageParser\Services\Parser($file);
$parser->setAuthor("Your Name");
$thread = $parser->parseThread();

$statistics = new \w\MessageParser\Services\Statistics($thread);

get_class_methods($statistics);
```

`php your_file.php`

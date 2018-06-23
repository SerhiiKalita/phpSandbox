# phpSandbox
php sandbox static analyzer



# example use

$allowed = array(['Yii', 't'], 'get_meta_tags', 'echo', 'eval', 'gfh', 'bad', 'hello', 'substr', 'array_merge', 'json_decode', 'file_get_contents', array('Foo', 'bar'), ['Foo', 'bad']);

phpSandbox::check('Blogger.php', $allowed);

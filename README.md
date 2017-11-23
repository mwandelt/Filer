# What is Filer?

Filer is a PHP class for safely writing and reading data files in a
multi-threaded web server environment. It is an alternative to using a
full-sized database system, which might me over-the-top when the amount of
stored data is small and the number of requests per hour is low.


## Basic Usage

First, you have to prepare an empty directory which is writable for PHP. Let's
say, the path is stored in a variable called `$storageDir`. If you want to store
some data, just do the following:

```php
require_once 'filer.class.php';
$filer = new Filer( $storageDir );
$data = 'This text should be stored.';
$filer->write( 'data.txt', $data );
```

If you want to read the data in later on, do the following:

```php
require_once 'filer.class.php';
$filer = new Filer( $storageDir );
$data = $filer->read('data.txt');
```

Filer expects your data to be a string, so if you want to store objects or
arrays you have to convert them to a string first, e.g. via `serialize()` or
`json_encode()`.


## About Locking and Unlocking

When writing to files in a multi-threaded environment special precautions have
to be taken to prevent simultaniously running processes from overwriting each
others data. Filer implements a locking mechanism to ensure, that only one
process at a time has write access to the storage directory. It creates an empty
`.lock` folder within the storage directory before actually changing anything in
it. When the `.lock` folder cannot be created --- which is the case when a
folder with this name already exists --- the writing process pauses and waits
until the existing folder has been deleted.

Filer takes care of the locking and unlocking automatically when you write to a
storage file, so normally, you do not have to deal with this mechanism. But
sometimes you might want to explicitly lock the storage directory, e.g. when you
read data from a file, do some modifications and write the modified data back to
the file. In this case it would be bad if another process changed the data file
between your read and write, because its changes would be overwritten by yours.

If you want to explicitly lock the storage directory, you can call the `lock`
method of your Filer object:

`$filer->lock();`

Alternatively, when you are going to read data anyway, you can call the `read`
method with a boolean TRUE as second argument:

`filer->read( 'data.txt', TRUE );`

An explicite lock will remain until your script shuts down or you explicitly
remove it:

`$filer->unlock();`


## Avoiding Infinite Locks

As said above, a lock created by the current script will be removed
automatically on shutdown, even if you forget to call the `unlock` method. But
when there is a power failure, or the script gets killed or crashes, the locks
would remain forever, blocking any subsequent attempts to write to the storage
directory. In order to avoid this scenario, Filer has an automatic unlock
feature. 

When trying to lock the storage directory, Filer will ignore an existing `.lock`
folder if it is older than 24 hours. You may adjust this by setting the
`autoUnlockPeriod` property of the Filer object to the desired number of
seconds:

`$filer->autoUnlockPeriod = 180;`

You should carefully consider which period is suitable for your project. If it
is too short, there might be occasions when a lock gets auto-removed although
the originating script is still running. If it is too long, certain functions of
your application might be blocked unneccessarily long after a crash. 


## Changing the Time-Out

If the `.lock` folder cannot be created, Filer will wait a second, then retry,
then wait another second, retry etc. By default this trial process lasts five
seconds, after which Filer will give up. If you want a shorter or longer
time-out period, you may set the `timeOutPeriod` property of the Filer object to
the desired number of seconds:

`$filer->timeOutPeriod = 10;`

Don't set this period too short. From a user's point of view it is less annoying
to wait some seconds for a reponse than to get an error message.


## Crash Recovery

Filer takes some precautions to prevent data loss in case of sudden shutdowns or
server crashes. When storing data the data will first be written to a temporary
file. Then, the original file will be renamed by adding a tilde (`~`) at the
beginning of its name. Finally, the temporary file will be renamed so that its
name conforms to the original file name. This procedure ensures that, at the
worst, a single write process might fail, but you will never loose your complete
data.

In the rare case that a crash occurs right after renaming the original file.
i.e. before the temporary file has been renamed, Filer will catch up on renaming
it the next time you try to read the file. 


## Securing Data Files

Generally, you should configure your webserver to deny access to the files in
the storage directory (e.g. via `.htaccess` files). Otherwise unauthorized
people could download your data files and see everything you have stored in
them. If you are in a shared hosting environment and have no facility for
setting-up directory protection, you still have a chance to prevent your data
files to be downloaded. Just let their names end with `.php`, and Filer will add
a short snippet of PHP code when writing data to them. The PHP code will produce
an error message whenever someone tries to download such a file. When reading
data files, Filer will automatically remove any PHP code snippet, so you won't
see any difference.


## Customising Prefixes and Names

If you want Filer to name the generated lock folder differently or use another
prefix for temporary files and backup files, just set the respective properties
of the Filer object. Have a look at the source code --- the names of the
properties should be pretty much self-explanatory.

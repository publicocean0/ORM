ORM
======



A lightweight nearly-zero-configuration object-relational mapper and fluent query builder for PHP5.

Tested on PHP 5.2.0+ - may work on earlier versions with PDO and the correct database drivers.

Released under a [GPL3 license].



Features
--------

* Makes simple queries and simple CRUD operations completely painless.
* Gets out of the way when more complex SQL is required.
* Built on top of [PDO](http://php.net/pdo).
* Uses [prepared statements](http://uk.php.net/manual/en/pdo.prepared-statements.php) throughout to protect against [SQL injection](http://en.wikipedia.org/wiki/SQL_injection) attacks.
* Requires no model classes, no XML configuration and no code generation: works out of the box, given only a connection string.
* Consists of just one class called `ORM`. Minimal global namespace pollution.
* Database agnostic. Currently supports SQLite and MySQL. May support others, please give it a try!

Let's See Some Code
-------------------

The first thing you need to know about ORM is that *you don't need to define any model classes to use it*. With almost every other ORM, the first thing to do is set up your models and map them to database tables (through configuration variables, XML files or similar). With ORM, you can start using the ORM straight away.

### Setup ###

First, `require` the ORM source file:

    require_once 'ORM.php';

    $orm = new ORM();
    
You may also need to pass a username and password to your database driver, using the `username` and `password` configuration options. For example, if you are using MySQL:


    $orm->configure('mysql:host=localhost;dbname=my_database');
    $orm->configure('username', 'database_user');
    $orm->configure('password', 'top_secret');

Also see "Configuration" section below.

### Querying ###

ORM provides a [*fluent interface*](http://en.wikipedia.org/wiki/Fluent_interface) to enable simple queries to be built without writing a single character of SQL. If you've used [jQuery](http://jquery.com) at all, you'll be familiar with the concept of a fluent interface. It just means that you can *chain* method calls together, one after another. This can make your code more readable, as the method calls strung together in order can start to look a bit like a sentence.

All ORM queries start with a call to the `for_table` static method on the ORM class. This tells the ORM which table to use when making the query. 

*Note that this method **does not** escape its query parameter and so the table name should **not** be passed directly from user input.*

Method calls which add filters and constraints to your query are then strung together. Finally, the chain is finished by calling either `find_one()` or `find_many()`, which executes the query and returns the result.

Let's start with a simple example. Say we have a table called `person` which contains the columns `id` (the primary key of the record - ORM assumes the primary key column is called `id` but this is configurable, see below), `name`, `age` and `gender`.

#### Single records ####

Any method chain that ends in `find_one()` will return either a *single* instance of the ORM class representing the database row you requested, or `false` if no matching record was found.

To find a single record where the `name` column has the value "Fred Bloggs":

    $person = $orm->for_table('person')->where('name', 'Fred Bloggs')->find_one();

This roughly translates into the following SQL: `SELECT * FROM person WHERE name = "Fred Bloggs"`

To find a single record by ID, you can pass the ID directly to the `find_one` method:

    $person = $orm->for_table('person')->find_one(5);

#### Multiple records ####

Any method chain that ends in `find_many()` will return an *array* of ORM class instances, one for each row matched by your query. If no rows were found, an empty array will be returned.

To find all records in the table:

    $people = $orm->for_table('person')->find_many();

To find all records where the `gender` is `female`:

    $females = $orm->for_table('person')->where('gender', 'female')->find_many();

##### As an associative array #####

You can also find many records as an associative array instead of ORM instances. To do this substitute any call to `find_many()` with `find_array()`.

    $females = $orm->for_table('person')->where('gender', 'female')->find_array();

This is useful if you need to serialise the the query output into a format like JSON and you do not need the ability to update the returned records.

#### Counting results ####

To return a count of the number of rows that would be returned by a query, call the `count()` method.

    $number_of_people = $orm->for_table('person')->count();

#### Filtering results ####

ORM provides a family of methods to extract only records which satisfy some condition or conditions. These methods may be called multiple times to build up your query, and ORM's fluent interface allows method calls to be *chained* to create readable and simple-to-understand queries.

##### *Caveats* #####

Only a subset of the available conditions supported by SQL are available when using ORM. Additionally, all the `WHERE` clauses will be `AND`ed together when the query is run. Support for `OR`ing `WHERE` clauses is not currently present.

These limits are deliberate: these are by far the most commonly used criteria, and by avoiding support for very complex queries, the ORM codebase can remain small and simple.

Some support for more complex conditions and queries is provided by the `where_raw` and `raw_query` methods (see below). If you find yourself regularly requiring more functionality than ORM can provide, it may be time to consider using a more full-featured ORM.

##### Equality: `where`, `where_equal`, `where_not_equal` #####

By default, calling `where` with two parameters (the column name and the value) will combine them using an equals operator (`=`). For example, calling `where('name', 'Fred')` will result in the clause `WHERE name = "Fred"`.

If your coding style favours clarity over brevity, you may prefer to use the `where_equal` method: this is identical to `where`.

The `where_not_equal` method adds a `WHERE column != "value"` clause to your query.

##### Shortcut: `where_id_is` #####

This is a simple helper method to query the table by primary key. Respects the ID column specified in the config.

##### Less than / greater than: `where_lt`, `where_gt`, `where_lte`, `where_gte` #####

There are four methods available for inequalities:

* Less than: `$people = $orm->for_table('person')->where_lt('age', 10)->find_many();`
* Greater than: `$people = $orm->for_table('person')->where_gt('age', 5)->find_many();`
* Less than or equal: `$people = $orm->for_table('person')->where_lte('age', 10)->find_many();`
* Greater than or equal: `$people = $orm->for_table('person')->where_gte('age', 5)->find_many();`

##### String comparision: `where_like` and `where_not_like` #####

To add a `WHERE ... LIKE` clause, use:

    $people = $orm->for_table('person')->where_like('name', '%fred%')->find_many();

Similarly, to add a `WHERE ... NOT LIKE` clause, use:

    $people = $orm->for_table('person')->where_not_like('name', '%bob%')->find_many();

##### Set membership: `where_in` and `where_not_in` #####

To add a `WHERE ... IN ()` or `WHERE ... NOT IN ()` clause, use the `where_in` and `where_not_in` methods respectively.

Both methods accept two arguments. The first is the column name to compare against. The second is an *array* of possible values.

    $people = $orm->for_table('person')->where_in('name', array('Fred', 'Joe', 'John'))->find_many();

##### Working with `NULL` values: `where_null` and `where_not_null` #####

To add a `WHERE column IS NULL` or `WHERE column IS NOT NULL` clause, use the `where_null` and `where_not_null` methods respectively. Both methods accept a single parameter: the column name to test.

##### Raw WHERE clauses #####

If you require a more complex query, you can use the `where_raw` method to specify the SQL fragment for the WHERE clause exactly. This method takes two arguments: the string to add to the query, and an (optional) array of parameters which will be bound to the string. If parameters are supplied, the string should contain question mark characters (`?`) to represent the values to be bound, and the parameter array should contain the values to be substituted into the string in the correct order.

This method may be used in a method chain alongside other `where_*` methods as well as methods such as `offset`, `limit` and `order_by_*`. The contents of the string you supply will be connected with preceding and following WHERE clauses with AND.

    $people = $orm->for_table('person')
                ->where('name', 'Fred')
                ->where_raw('(`age` = ? OR `age` = ?)', array(20, 25))
                ->order_by_asc('name')
                ->find_many();

    // Creates SQL:
    SELECT * FROM `person` WHERE `name` = "Fred" AND (`age` = 20 OR `age` = 25) ORDER BY `name` ASC;

Note that this method only supports "question mark placeholder" syntax, and NOT "named placeholder" syntax. This is because PDO does not allow queries that contain a mixture of placeholder types. Also, you should ensure that the number of question mark placeholders in the string exactly matches the number of elements in the array.

If you require yet more flexibility, you can manually specify the entire query. See *Raw queries* below.

##### Limits and offsets #####

*Note that these methods **do not** escape their query parameters and so these should **not** be passed directly from user input.*

The `limit` and `offset` methods map pretty closely to their SQL equivalents.

    $people = $orm->for_table('person')->where('gender', 'female')->limit(5)->offset(10)->find_many();

##### Ordering #####

*Note that these methods **do not** escape their query parameters and so these should **not** be passed directly from user input.*

Two methods are provided to add `ORDER BY` clauses to your query. These are `order_by_desc` and `order_by_asc`, each of which takes a column name to sort by. The column names will be quoted.

    $people = $orm->for_table('person')->order_by_asc('gender')->order_by_desc('name')->find_many();

If you want to order by something other than a column name, then use the `order_by_expr` method to add an unquoted SQL expression as an `ORDER BY` clause.

    $people = $orm->for_table('person')->order_by_expr('SOUNDEX(`name`)')->find_many();

#### Grouping ####

*Note that this method **does not** escape it query parameter and so this should **not** by passed directly from user input.*

To add a `GROUP BY` clause to your query, call the `group_by` method, passing in the column name. You can call this method multiple times to add further columns.

    $people = $orm->for_table('person')->where('gender', 'female')->group_by('name')->find_many();


#### Result columns ####

By default, all columns in the `SELECT` statement are returned from your query. That is, calling:

    $people = $orm->for_table('person')->find_many();

Will result in the query:

    SELECT * FROM `person`;

The `select` method gives you control over which columns are returned. Call `select` multiple times to specify columns to return or use [`select_many`](#shortcuts-for-specifying-many-columns) to specify many columns at once.

    $people = $orm->for_table('person')->select('name')->select('age')->find_many();

Will result in the query:

    SELECT `name`, `age` FROM `person`;

Optionally, you may also supply a second argument to `select` to specify an alias for the column:

    $people = $orm->for_table('person')->select('name', 'person_name')->find_many();

Will result in the query:

    SELECT `name` AS `person_name` FROM `person`;

Column names passed to `select` are quoted automatically, even if they contain `table.column`-style identifiers:

    $people = $orm->for_table('person')->select('person.name', 'person_name')->find_many();

Will result in the query:

    SELECT `person`.`name` AS `person_name` FROM `person`;

If you wish to override this behaviour (for example, to supply a database expression) you should instead use the `select_expr` method. Again, this takes the alias as an optional second argument. You can specify multiple expressions by calling `select_expr` multiple times or use [`select_many_expr`](#shortcuts-for-specifying-many-columns) to specify many expressions at once.

    // NOTE: For illustrative purposes only. To perform a count query, use the count() method.
    $people_count = $orm->for_table('person')->count();

Will result in the query:

    SELECT COUNT(*) AS `count` FROM `person`;

##### Shortcuts for specifying many columns #####

`select_many` and `select_many_expr` are very similar, but they allow you to specify more than one column at once. For example:

    $people = $orm->for_table('person')->select_many('name', 'age')->find_many();

Will result in the query:

    SELECT `name`, `age` FROM `person`;


#### DISTINCT ####

To add a `DISTINCT` keyword before the list of result columns in your query, add a call to `distinct()` to your query chain.

    $distinct_names = $orm->for_table('person')->distinct()->select('name')->find_many();

This will result in the query:

    SELECT DISTINCT `name` FROM `person`;

#### Joins ####

ORM has a family of methods for adding different types of `JOIN`s to the queries it constructs:

Methods: `join`, `inner_join`, `left_outer_join`, `right_outer_join`, `full_outer_join`.

Each of these methods takes the same set of arguments. The following description will use the basic `join` method as an example, but the same applies to each method.

The first two arguments are mandatory. The first is the name of the table to join, and the second supplies the conditions for the join. The recommended way to specify the conditions is as an *array* containing three components: the first column, the operator, and the second column. The table and column names will be automatically quoted. For example:

    $results = $orm->for_table('person')->join('person_profile', array('person.id', '=', 'person_profile.person_id'))->find_many();

It is also possible to specify the condition as a string, which will be inserted as-is into the query. However, in this case the column names will **not** be escaped, and so this method should be used with caution.

    // Not recommended because the join condition will not be escaped.
    $results = $orm->for_table('person')->join('person_profile', 'person.id = person_profile.person_id')->find_many();

The `join` methods also take an optional third parameter, which is an `alias` for the table in the query. This is useful if you wish to join the table to *itself* to create a hierarchical structure. In this case, it is best combined with the `table_alias` method, which will add an alias to the *main* table associated with the ORM, and the `select` method to control which columns get returned.

    $results = $orm->for_table('person')
        ->table_alias('p1')
        ->select('p1.*')
        ->select('p2.name', 'parent_name')
        ->join('person', array('p1.parent', '=', 'p2.id'), 'p2')
        ->find_many();

#### Aggregate functions ####

There is support for `MIN`, `AVG`, `MAX` and `SUM` in addition to `COUNT` (documented earlier).

To return a minimum value of column, call the `min()` method.

    $min = $orm->for_table('person')->min('height');

The other functions (`AVG`, `MAX` and `SUM`) work in exactly the same manner. Supply a column name to perform the aggregate function on and it will return an integer.

#### Raw queries ####

If you need to perform more complex queries, you can completely specify the query to execute by using the `raw_query` method. This method takes a string and optionally an array of parameters. The string can contain placeholders, either in question mark or named placeholder syntax, which will be used to bind the parameters to the query.

    $people = $orm->for_table('person')->raw_query('SELECT p.* FROM person p JOIN role r ON p.role_id = r.id WHERE r.name = :role', array('role' => 'janitor'))->find_many();

The ORM class instance(s) returned will contain data for all the columns returned by the query. Note that you still must call `for_table` to bind the instances to a particular table, even though there is nothing to stop you from specifying a completely different table in the query. This is because if you wish to later called `save`, the ORM will need to know which table to update.

Note that using `raw_query` is advanced and possibly dangerous, and ORM does not make any attempt to protect you from making errors when using this method. If you find yourself calling `raw_query` often, you may have misunderstood the purpose of using an ORM, or your application may be too complex for ORM. Consider using a more full-featured database abstraction system.

### Getting data from objects ###

Once you've got a set of records (objects) back from a query, you can access properties on those objects (the values stored in the columns in its corresponding table) in two ways: by using the `get` method, or simply by accessing the property on the object directly:

    $person = $orm->for_table('person')->find_one(5);

    // The following two forms are equivalent
    $name = $person->get('name');
    $name = $person->name;

You can also get the all the data wrapped by an ORM instance using the `as_array` method. This will return an associative array mapping column names (keys) to their values.

The `as_array` method takes column names as optional arguments. If one or more of these arguments is supplied, only matching column names will be returned.

    $person = $orm->for_table('person')->create();

    $person->first_name = 'Fred';
    $person->surname = 'Bloggs';
    $person->age = 50;

    // Returns array('first_name' => 'Fred', 'surname' => 'Bloggs', 'age' => 50)
    $data = $person->as_array();

    // Returns array('first_name' => 'Fred', 'age' => 50)
    $data = $person->as_array('first_name', 'age');

### Updating records ###

To update the database, change one or more of the properties of the object, then call the `save` method to commit the changes to the database. Again, you can change the values of the object's properties either by using the `set` method or by setting the value of the property directly. By using the `set` method it is also possible to update multiple properties at once, by passing in an associative array:

    $person = $orm->for_table('person')->find_one(5);

    // The following two forms are equivalent
    $person->set('name', 'Bob Smith');
    $person->age = 20;

    // This is equivalent to the above two assignments
    $person->set(array(
        'name' => 'Bob Smith',
        'age'  => 20
    ));

    // Syncronise the object with the database
    $person->save();

### Creating new records ###

To add a new record, you need to first create an "empty" object instance. You then set values on the object as normal, and save it.

    $person = $orm->for_table('person')->create();

    $person->name = 'Joe Bloggs';
    $person->age = 40;

    $person->save();

After the object has been saved, you can call its `id()` method to find the autogenerated primary key value that the database assigned to it.


### Deleting records ###

To delete an object from the database, simply call its `delete` method.

    $person = $orm->for_table('person')->find_one(5);
    $person->delete();

To delete more than one object from the database, build a query:

    $person = $orm->for_table('person')
        ->where_equal('zipcode', 55555)
        ->delete();

### Transactions ###

ORM doesn't supply any extra methods to deal with transactions, but it's very easy to use PDO's built-in methods:

    // Start a transaction
    $orm->get_PDO()->beginTransaction();

    // Commit a transaction
    $orm->get_PDO()->commit();

    // Roll back a transaction
    $orm->get_PDO()->rollBack();

For more details, see [the PDO documentation on Transactions](http://www.php.net/manual/en/pdo.transactions.php).

### Configuration ###

Other than setting the DSN string for the database connection (see above), the `configure` method can be used to set some other simple options on the ORM class. Modifying settings involves passing a key/value pair to the `configure` method, representing the setting you wish to modify and the value you wish to set it to.

    $orm->configure('setting_name', 'value_for_setting');

A shortcut is provided to allow passing multiple key/value pairs at once.

    $orm->configure(array(
        'setting_name_1' => 'value_for_setting_1', 
        'setting_name_2' => 'value_for_setting_2', 
        'etc' => 'etc'
    ));

#### Database authentication details ####

Settings: `username` and `password`

Some database adapters (such as MySQL) require a username and password to be supplied separately to the DSN string. These settings allow you to provide these values. A typical MySQL connection setup might look like this:

    $orm->configure('mysql:host=localhost;dbname=my_database');
    $orm->configure('username', 'database_user');
    $orm->configure('password', 'top_secret');

Or you can combine the connection setup into a single line using the configuration array shortcut:

    $orm->configure(array(
        'mysql:host=localhost;dbname=my_database', 
        'username' => 'database_user', 
        'password' => 'top_secret'
    ));

#### PDO Driver Options ####

Setting: `driver_options`

Some database adapters require (or allow) an array of driver-specific configuration options. This setting allows you to pass these options through to the PDO constructor. For more information, see [the PDO documentation](http://www.php.net/manual/en/pdo.construct.php). For example, to force the MySQL driver to use UTF-8 for the connection:

    $orm->configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));


#### PDO Error Mode ####

Setting: `error_mode`

This can be used to set the `PDO::ATTR_ERRMODE` setting on the database connection class used by ORM. It should be passed one of the class constants defined by PDO. For example:

    $orm->configure('error_mode', PDO::ERRMODE_WARNING);

The default setting is `PDO::ERRMODE_EXCEPTION`. For full details of the error modes available, see [the PDO documentation](http://uk2.php.net/manual/en/pdo.setattribute.php).

#### Identifier quote character ####

Setting: `identifier_quote_character`

Set the character used to quote identifiers (eg table name, column name). If this is not set, it will be autodetected based on the database driver being used by PDO.

#### ID Column ####

By default, the ORM assumes that all your tables have a primary key column called `id`. There are two ways to override this: for all tables in the database, or on a per-table basis.

Setting: `id_column`

This setting is used to configure the name of the primary key column for all tables. If your ID column is called `primary_key`, use:

    $orm->configure('id_column', 'primary_key');


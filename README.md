# aneya PHP Framework

TLDR; Aneya is a full-fledged, developer-first PHP framework, compatible with PHP version 8.2.
It's build with flexibility, customization, scaling, security, rapid development and performance in its core.

## Features

At a glance, aneya is bundled with a wide list of turn-key features (some of them are quite exotic), such as:
* Modular, event-driven design
* RESTful APIs support, with CRUD automation
* Seamless ORM support
* Dynamic, rule-based routing
* Seamless, cross-DBMS database access and abstraction layer (even supports queries, joins & transactions between different DB vendors)
* Multilayered security scheme
* Multi-subproject support with namespaces-based isolation
* Seamless Back-end <-> Front-end serialization/deserialization
* Seamless M17N & I18N support
* Multilingual content management
* Internal, highly powerful templating engine
* Filesystem abstraction
* Caching & performance tuning
* Advanced class autoloading
* Seamless integration with Composer


## Quick Examples

Defining an API route:
```php
/** @var /aneya/API/ApiController $controller */
$controller->routes->add(new ApiRoute(
    ['#^/api/v(?<version>[0-9]+[\.0-9]*?)/auth/?(\?.*)?$#',
     '#^/api/v(?<version>[0-9]+[\.0-9]*?)/user/signin/?(\?.*)?$#'],
    Request::MethodPost, 'namespace', ['user role1', 'user role2'], ['permission1', 'permission2'], null, null, 'auth route tag', ApiRoute::AuthTypePasswordCredentials));
```

Get the currently signed-in user instance in a "frontend" namespace:
```php
/** @var /aneya/Security/User $user */
$user = User::get('frontend');
```

Query a database (join an `accounts` with a `contacts` table):
```php
// The framework will take care of joining the tables properly,
// based on their referential integrity found in the database
$ds = CMS::db('my_schema')->schema->getDataSet(
    // tables to include in the dataset
    ['accounts' => 'A', 'contacts' => 'C'],
    // columns to include in the dataset
    ['a.account_id', 'a.name', 'C.contact_id', 'C.account_id' => 'c_account_id', 'C.first_name']
);
$row = $ds->retrieve(
    // Apply filtering (accounts starting with 'a')
    new DataFilterCollection([new DataFilter($ds->columns->get('name'), DataFilter::StartsWith, 'a')]),
    // Apply sorting (by account's name, ascending)
    new DataSorting($ds->columns->get('name'), DataSorting::Ascending)
)->rows->first(/* a callable here could examine further which row should match first */);
$row->setValue('name', 'new name');
$row->setValue('first_name', 'new first name');

// Saves both tables in a single SQL transaction
$row->save();
```

Join tables from different RDBMSes (joins are handled at the framework level):
```php
// Get "contacts" table from PostgreSQL and "payments" table from Oracle
$contacts = CMS::db('pgsql_crm')->schema->getDataSet(['accounts' => 'A', 'contacts' => 'C'], ['a.*', 'C.contact_id', 'C.account_id' => 'c_account_id']);
$payments = CMS::db('oracle_accounting')->schema->getDataSet('payments', ['payment_id', 'account_id' => 'p_account_id', 'status']);

// The dataset that holds all tables
$ds = new DataSet();
$ds->tables->addRange([$contacts, $payments]);

// Explicitly define the join relation between the two tables
$join = $ds->relations->add(new DataRelation($contacts, $payments, DataRelation::JoinInner));
$r->link($contacts->columns->get('account_id'), $payments->columns->get('p_account_id'));

// Select a record from Oracle & PostgreSQL at one shot and make few changes
$row = $ds->retrieve(/* specify some id filtering */);
$row->setValue('a contacts column', 'new value');
$row->setValue('a payments column', 'another value');

// Now save changes in both PostgreSQL & Oracle in a single transaction
$status = $row->save();
if ($status->isOK) {
    // Changes were committed to both databases
}
else {
    // If the INSERT/UPDATE fails in any on the tables,
    // the framework will roll back the transaction to both databases
}
```

Setup ORM in a class and retrieve/save changes to an instantiated object:
```php
class Contact extends CoreObject implements IDataObject, IStorable {
	use Storable; // Storable trait automates all ORM-related setups out-of-the-box

    public int $id;
    public string $firstName;
    public string $lastName;
    public string $email;
    public string $phone;
    
    // Override the Storable::onORM method to tell the framework which exact table shall this class be related to
    protected static function onORM(): DataObjectMapping {
        $ds = static::classDataSet(CMS::db()->schema->getDataSet('contacts', null /* fetch all available fields */, true /* true to name all columns in camelCase */));
        $ds->mapClass(static::class);
		$orm = ORM::dataSetToMapping($ds, static::class);
		
		// In case a class property has different name from the mapped table
		$orm->getProperty('contactId')->propertyName = 'id';
		
		return $orm;
    }
}

// Now we can just use ORM out of the box
$contact = Contact::load(1 /* passing the primary key(s) as params */);
$contact->firstName = 'foo';

// This will trigger saving all object's property values to the database
$contact->save();
```

Managing and querying the filesystem:
```php
// Get framework's FileSystem class instance
$fs = CMS::filesystem();

// Generate a random file name prefixed by "file_" and located under project's /tmp
$localFile = $fs->tempnam('/tmp', 'file_');
// Upload the file
$fs->upload($_FILES['file']['tmp_name'], $localFile);

// List all files in '/docs', under project's root folder
$files = $fs->ls('/docs');

// Read a file
$data = $fs->read('/docs/file.xlsx');

// Send a file to the browser
$fs->download(new File('/path/to/file'));
```

## Documentation

### [Installation](docs/install.md)

### [Structure](docs/structure.md)

### [Modules](docs/modules.md)

### [Bootstrapping](docs/bootstrap.md)

### [Filesystem](docs/filesystem.md)

### [Routing](docs/routing.md)

### [Database](docs/database.md)

### [Security](docs/security.md)

### [M17N & I18N](docs/m17n.md)

### [Template Engine](docs/snippets.md)

## License
Aneya framework is now publicly available under GNU Affero General Public License version 3 ([AGPL](https://www.gnu.org/licenses/agpl-3.0.txt)).

If you need to use this framework to build commercial software, you may get in touch to grant a more
suitable license under a special agreement.

## About
Aneya was kicked off by its sole author as an internal project back in 2007, developed to power large
web projects (e-commerce solutions and marketplaces, custom web applications, SaaS etc.) that required
coping with high customization levels, without compromising developers' experience. Since its kick off,
aneya continued to evolve every year and turned into a complete, standalone PHP framework.

It's architecture was designed to allow extending and customizing the default framework's behaviour
in a no lock-in concept. Contrary, it gives developers full freedom to either use its available
modules and easily customize/override their behaviour, select their desired and best-fitted project
structure, or completely bypass parts of the framework in favour of other composer packages.

As I have turned into adopting newer and more high-end technologies than PHP for our projects, it's
been a while since aneya was just kept in the shelf as a legacy internal framework. Hence, it was
about time to open its code to the public, mostly as a reference piece of past work. Still, it may
inspire framework developers into adopting some of its logic and way of handling project requirements.

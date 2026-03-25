# PHP Theory Questions and Answers

## Table of Contents
1. [Basic PHP Concepts](#basic-php-concepts)
2. [Variables and Data Types](#variables-and-data-types)
3. [Functions and Scope](#functions-and-scope)
4. [Arrays](#arrays)
5. [Strings](#strings)
6. [Object-Oriented Programming (OOP)](#object-oriented-programming)
7. [Error Handling](#error-handling)
8. [Sessions and Cookies](#sessions-and-cookies)
9. [Database and SQL](#database-and-sql)
10. [Security](#security)
11. [File Handling](#file-handling)
12. [Regular Expressions](#regular-expressions)
13. [Intermediate Concepts](#intermediate-concepts)
14. [Advanced Concepts](#advanced-concepts)

---

## BASIC PHP CONCEPTS

### Q1: What is PHP? What are its advantages?
**Answer:**
- PHP stands for **Hypertext Preprocessor**
- Server-side scripting language used for web development
- **Advantages:**
  - Open-source and free
  - Easy to learn and use
  - Runs on all major operating systems
  - Compatible with all major browsers
  - Supports multiple databases (MySQL, PostgreSQL, etc.)
  - Can generate dynamic page content
  - Can send and receive cookies
  - Can control user access with sessions
  - Processes form data
  - Can output different content types (HTML, XML, JSON, etc.)

### Q2: What is the difference between PHP and HTML?
**Answer:**
| PHP | HTML |
|-----|------|
| Server-side scripting language | Client-side markup language |
| Dynamic - generates content on server | Static - fixed content |
| Requires server to execute | Runs directly in browser |
| Code is hidden from user | Code is visible in browser |
| Can interact with databases | Cannot interact with databases |

### Q3: What are the main features of PHP?
**Answer:**
1. **Simplicity** - Easy syntax similar to C
2. **Efficiency** - Lightweight and fast
3. **Security** - Built-in security features
4. **Compatibility** - Works with HTML, CSS, JavaScript
5. **Database Support** - Works with various databases
6. **Flexibility** - Can be embedded in HTML
7. **Platform Independent** - Runs on Linux, Windows, Mac, etc.
8. **Real-time Monitoring** - Error logging capabilities

### Q4: What is the difference between include and require in PHP?
**Answer:**
```php
// include - Warning if file not found, script continues
include 'header.php';
include_once 'header.php'; // Includes only once

// require - Fatal error if file not found, script stops
require 'config.php';
require_once 'config.php'; // Requires only once
```
- **include** - Non-critical files, script continues on error
- **require** - Critical files, script stops on error
- **include_once/require_once** - Prevents duplicate inclusions

### Q5: What is $GLOBALS in PHP?
**Answer:**
- Super global array that contains all global variables
- Can be used to access global variables inside functions
- Example:
```php
$name = "John";

function myFunction() {
    echo $GLOBALS['name']; // Outputs: John
}

myFunction();
```

---

## VARIABLES AND DATA TYPES

### Q6: What are the data types in PHP?
**Answer:**
1. **String** - "Hello World"
2. **Integer** - 1234
3. **Float** - 12.34
4. **Boolean** - true or false
5. **Array** - [1, 2, 3]
6. **Object** - Instance of a class
7. **NULL** - No value assigned
8. **Resource** - Reference to external resource (database, file)

### Q7: What is the difference between var_dump() and print_r()?
**Answer:**
```php
$array = [1, 2, 3];

var_dump($array);
// Output: array(3) { [0]=> int(1) [1]=> int(2) [2]=> int(3) }

print_r($array);
// Output: Array ( [0] => 1 [1] => 2 [2] => 3 )
```
- **var_dump()** - Shows type and value, better for debugging
- **print_r()** - Shows human-readable format, easier to read

### Q8: What is variable scope in PHP?
**Answer:**
1. **Local** - Inside a function
2. **Global** - Outside of any function
3. **Static** - Retains value between function calls
4. **Superglobal** - Available everywhere ($_GET, $_POST, etc.)

```php
$global_var = "Global";

function testScope() {
    $local_var = "Local"; // Only inside function
    global $global_var;   // Access global variable
    echo $global_var;     // Works
    echo $local_var;      // Works
}

echo $global_var;  // Works
echo $local_var;   // Error - undefined
```

### Q9: What is the difference between == and === in PHP?
**Answer:**
```php
$a = 5;
$b = "5";

var_dump($a == $b);   // true (value comparison, type juggling)
var_dump($a === $b);  // false (strict comparison, same type)

// Examples:
0 == false;     // true
0 === false;    // false
"1" == true;    // true
"1" === true;   // false
```
- **==** - Loose comparison (ignores type)
- **===** - Strict comparison (type and value must match)

### Q10: What is type casting in PHP?
**Answer:**
```php
// Convert one type to another
(int) "123"         // 123 (integer)
(float) "12.34"     // 12.34 (float)
(string) 123        // "123" (string)
(bool) 1            // true (boolean)
(array) "test"      // ["test"] (array)
(object) ["a" => 1] // Object with property 'a'
```

---

## FUNCTIONS AND SCOPE

### Q11: What is the difference between pass by value and pass by reference?
**Answer:**
```php
// Pass by Value (copy)
function changeByValue($param) {
    $param = 100;
}
$x = 10;
changeByValue($x);
echo $x; // 10 (unchanged)

// Pass by Reference (original)
function changeByReference(&$param) {
    $param = 100;
}
$y = 10;
changeByReference($y);
echo $y; // 100 (changed)
```
- **Pass by Value** - Function works with copy of variable
- **Pass by Reference** - Function works with original variable (use &)

### Q12: What is a default parameter in PHP?
**Answer:**
```php
function greet($name = "Guest") {
    echo "Hello " . $name;
}

greet();          // Hello Guest
greet("John");    // Hello John
```
- Default parameters provide default values if argument not provided

### Q13: What are variadic functions in PHP?
**Answer:**
```php
// Multiple arguments using ... (splat operator)
function sum(...$numbers) {
    $total = 0;
    foreach ($numbers as $num) {
        $total += $num;
    }
    return $total;
}

echo sum(1, 2, 3, 4, 5); // 15

// Unpacking array
$numbers = [1, 2, 3];
echo sum(...$numbers); // 6
```

### Q14: What is the difference between return and echo?
**Answer:**
```php
function getValue() {
    return 123;  // Returns value, doesn't print
}

function printValue() {
    echo 123;    // Prints value immediately
}

$result = getValue();
echo $result; // Works

$result = printValue(); // Prints 123
echo $result; // null (nothing returned)
```
- **return** - Returns value to caller, can be stored
- **echo** - Prints output immediately, no return

### Q15: What is an anonymous function (closure) in PHP?
**Answer:**
```php
// Anonymous function (no name)
$greet = function($name) {
    return "Hello " . $name;
};

echo $greet("John"); // Hello John

// Closure with use clause
$message = "Hello";
$greet = function($name) use ($message) {
    return $message . " " . $name;
};

echo $greet("John"); // Hello John
```

---

## ARRAYS

### Q16: What are the different types of arrays in PHP?
**Answer:**
1. **Indexed Array** - Elements accessed by numeric index
```php
$colors = ["Red", "Green", "Blue"];
echo $colors[0]; // Red
```

2. **Associative Array** - Elements accessed by key
```php
$person = ["name" => "John", "age" => 30];
echo $person["name"]; // John
```

3. **Multidimensional Array** - Array of arrays
```php
$matrix = [
    [1, 2, 3],
    [4, 5, 6],
    [7, 8, 9]
];
echo $matrix[0][1]; // 2
```

### Q17: What is the difference between array_merge and array_combine?
**Answer:**
```php
// array_merge - Combines arrays
$array1 = [1, 2, 3];
$array2 = [4, 5, 6];
$result = array_merge($array1, $array2);
// [1, 2, 3, 4, 5, 6]

// array_combine - Creates array from keys and values
$keys = ["a", "b", "c"];
$values = [1, 2, 3];
$result = array_combine($keys, $values);
// ["a" => 1, "b" => 2, "c" => 3]
```

### Q18: What are array functions in PHP?
**Answer:**
Common array functions:
- `count()` - Count elements
- `array_push()` - Add element to end
- `array_pop()` - Remove element from end
- `array_shift()` - Remove element from beginning
- `array_unshift()` - Add element to beginning
- `array_key_exists()` - Check if key exists
- `in_array()` - Check if value exists
- `array_keys()` - Get all keys
- `array_values()` - Get all values
- `array_reverse()` - Reverse array
- `array_slice()` - Extract portion of array
- `array_splice()` - Remove and replace portion
- `sort()` - Sort ascending
- `rsort()` - Sort descending
- `array_map()` - Apply function to elements
- `array_filter()` - Filter elements

### Q19: What is the difference between array_slice and array_splice?
**Answer:**
```php
$arr = [1, 2, 3, 4, 5];

// array_slice - returns extracted portion (non-destructive)
$slice = array_slice($arr, 1, 2);
// $slice = [2, 3]
// $arr = [1, 2, 3, 4, 5] (unchanged)

// array_splice - removes and replaces (destructive)
$splice = array_splice($arr, 1, 2, ['a', 'b']);
// $splice = [2, 3]
// $arr = [1, 'a', 'b', 4, 5] (modified)
```

### Q20: What is isset() and empty() difference?
**Answer:**
```php
$var = 0;

isset($var);  // true - variable exists
empty($var);  // true - variable is empty (0, "", null, false)

$var = null;
isset($var);  // false - variable not set
empty($var);  // true - variable is empty

// unset removes variable
unset($var);
isset($var);  // false
```

---

## STRINGS

### Q21: What are common string functions in PHP?
**Answer:**
```php
strlen("Hello");           // 5
strtoupper("hello");       // HELLO
strtolower("HELLO");       // hello
ucfirst("hello");          // Hello
ucwords("hello world");    // Hello World
trim("  hello  ");         // "hello"
ltrim("  hello");          // "hello  "
rtrim("hello  ");          // "hello"
str_replace("a", "b", "cat"); // "cbt"
substr("hello", 0, 3);     // "hel"
strpos("hello", "l");      // 2
explode(",", "a,b,c");    // ["a", "b", "c"]
implode(",", ["a", "b"]); // "a,b"
str_repeat("ab", 3);       // "ababab"
```

### Q22: What is the difference between substr and strpos?
**Answer:**
```php
// substr - extracts substring
substr("hello", 1, 3);     // "ell" (starts at 1, length 3)

// strpos - finds position of substring
strpos("hello", "l");      // 2 (position where "l" is found)
strpos("hello", "l", 3);   // 3 (search from position 3)

// Returns false if not found
strpos("hello", "x");      // false
```

### Q23: What are string escape sequences?
**Answer:**
```php
echo "Hello \"World\"";    // Hello "World"
echo "Hello \n World";     // Hello (newline) World
echo "Hello \t World";     // Hello (tab) World
echo "Hello \\ World";     // Hello \ World
echo "Hello \$ sign";      // Hello $ sign
echo 'Hello $name';        // Hello $name (single quotes - literal)
echo "Hello $name";        // Hello John (double quotes - parse variables)
```

### Q24: What is the difference between single and double quotes?
**Answer:**
```php
$name = "John";

// Single quotes - literal (no parsing)
echo 'Name: $name';        // Name: $name

// Double quotes - parses variables and escape sequences
echo "Name: $name";        // Name: John
echo "Newline: \n here";   // Newline: (new line) here

// Heredoc (like double quotes)
$text = <<<EOT
Hello $name
This is multiple lines
EOT;

// Nowdoc (like single quotes)
$text = <<<'EOT'
Hello $name
This is literal
EOT;
```

---

## OBJECT-ORIENTED PROGRAMMING

### Q25: What are classes and objects in PHP?
**Answer:**
```php
class Car {
    public $brand;
    
    public function __construct($brand) {
        $this->brand = $brand;
    }
    
    public function display() {
        echo "Brand: " . $this->brand;
    }
}

$myCar = new Car("Toyota"); // object creation
$myCar->display();           // Brand: Toyota
```
- **Class** - Blueprint/template
- **Object** - Instance of a class

### Q26: What are access modifiers in PHP?
**Answer:**
```php
class Demo {
    public $public_var = "Public";        // Accessible everywhere
    protected $protected_var = "Protected"; // Only in class and subclasses
    private $private_var = "Private";     // Only in this class
}

$obj = new Demo();
echo $obj->public_var;      // Works
echo $obj->protected_var;   // Error
echo $obj->private_var;     // Error
```

### Q27: What is inheritance in PHP?
**Answer:**
```php
class Animal {
    public function eat() {
        echo "Eating...";
    }
}

class Dog extends Animal {
    public function bark() {
        echo "Woof!";
    }
}

$dog = new Dog();
$dog->eat();   // Inherited from Animal
$dog->bark();  // Own method
```
- **Single Inheritance** - Child extends one parent
- **Multilevel Inheritance** - A extends B, B extends C
- **Hierarchical Inheritance** - Multiple classes extend one parent

### Q28: What is polymorphism in PHP?
**Answer:**
```php
class Shape {
    public function area() {
        return 0;
    }
}

class Circle extends Shape {
    private $radius;
    
    public function __construct($radius) {
        $this->radius = $radius;
    }
    
    public function area() {
        return 3.14 * $this->radius * $this->radius;
    }
}

class Rectangle extends Shape {
    private $length, $width;
    
    public function __construct($length, $width) {
        $this->length = $length;
        $this->width = $width;
    }
    
    public function area() {
        return $this->length * $this->width;
    }
}

$circle = new Circle(5);
$rectangle = new Rectangle(4, 5);

echo $circle->area();      // 78.5
echo $rectangle->area();   // 20
```
- Same method name, different behavior in different classes

### Q29: What are abstract classes and interfaces?
**Answer:**
```php
// Abstract Class
abstract class Vehicle {
    abstract public function start();
    
    public function stop() {
        echo "Stopping...";
    }
}

class Car extends Vehicle {
    public function start() {
        echo "Car starting...";
    }
}

// Interface
interface Drivable {
    public function drive();
    public function park();
}

class Truck implements Drivable {
    public function drive() {
        echo "Truck driving...";
    }
    
    public function park() {
        echo "Truck parking...";
    }
}
```
- **Abstract Class** - Cannot be instantiated, has abstract and concrete methods
- **Interface** - All methods are abstract, defines contract

### Q30: What is static in PHP?
**Answer:**
```php
class Counter {
    public static $count = 0;
    
    public static function increment() {
        self::$count++;
    }
    
    public function instanceMethod() {
        echo self::$count;  // Access static variable
    }
}

Counter::$count = 5;      // Access static property
Counter::increment();       // Call static method

echo Counter::$count;       // 6
```
- **Static properties/methods** - Shared by all objects
- **Access using ::** (scope resolution operator)

### Q31: What is $this and self in PHP?
**Answer:**
```php
class MyClass {
    public $name = "John";
    public static $static_name = "Jane";
    
    public function test() {
        echo $this->name;           // John (current object property)
        echo self::$static_name;    // Jane (class static property)
        echo static::$static_name;  // Late static binding
    }
}

$obj = new MyClass();
$obj->test();
```
- **$this** - Refers to current object
- **self** - Refers to current class (static context)
- **static** - Late static binding (in inheritance)

### Q32: What is the difference between __construct and __destruct?
**Answer:**
```php
class Demo {
    public function __construct() {
        echo "Object created";
    }
    
    public function __destruct() {
        echo "Object destroyed";
    }
}

$obj = new Demo();  // Output: Object created
unset($obj);        // Output: Object destroyed
```
- **__construct()** - Called when object is created
- **__destruct()** - Called when object is destroyed

### Q33: What are magic methods in PHP?
**Answer:**
```php
class Magic {
    private $data = [];
    
    public function __get($name) {
        return $this->data[$name] ?? null;
    }
    
    public function __set($name, $value) {
        $this->data[$name] = $value;
    }
    
    public function __isset($name) {
        return isset($this->data[$name]);
    }
    
    public function __unset($name) {
        unset($this->data[$name]);
    }
    
    public function __call($name, $args) {
        echo "Method $name called";
    }
    
    public function __toString() {
        return "Magic Object";
    }
}

$obj = new Magic();
$obj->name = "John";           // __set()
echo $obj->name;               // __get()
isset($obj->name);             // __isset()
$obj->undefined_method();      // __call()
echo $obj;                     // __toString()
```

---

## ERROR HANDLING

### Q34: What are the types of errors in PHP?
**Answer:**
1. **Fatal Error** - Script stops
2. **Warning** - Script continues, but something is wrong
3. **Notice** - Non-critical issue, script continues
4. **Parse Error** - Syntax error, script stops
5. **Strict Error** - Deprecated code

### Q35: What is try-catch-finally?
**Answer:**
```php
try {
    if ($x == 0) {
        throw new Exception("Cannot divide by zero");
    }
    echo 10 / $x;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} finally {
    echo "Cleanup code";
}
```
- **try** - Code that might throw exception
- **catch** - Handle exception
- **finally** - Executes regardless of exception

### Q36: What is error_reporting?
**Answer:**
```php
// Report all errors
error_reporting(E_ALL);

// Report everything except notices
error_reporting(E_ALL & ~E_NOTICE);

// Display errors on screen
ini_set('display_errors', '1');

// Log errors to file
ini_set('log_errors', '1');
ini_set('error_log', '/path/to/error.log');
```

### Q37: What is set_error_handler?
**Answer:**
```php
function customError($errno, $errstr) {
    echo "Error: " . $errstr;
}

set_error_handler("customError");

echo $undefined_var;  // Calls customError instead of warning
```

---

## SESSIONS AND COOKIES

### Q38: What is the difference between sessions and cookies?
**Answer:**
| Sessions | Cookies |
|----------|---------|
| Server-side storage | Client-side storage |
| Data in $_SESSION | Data in $_COOKIE |
| More secure | Less secure |
| Stored until session ends | Persistent (can expire) |
| Cannot be viewed/modified by user | Can be viewed/modified by user |
| Uses session_start() | Automatically sent |

### Q39: How to create and use sessions?
**Answer:**
```php
// Start session (must be first)
session_start();

// Set session variable
$_SESSION['username'] = 'John';
$_SESSION['user_id'] = 123;

// Access session variable
echo $_SESSION['username'];

// Unset session variable
unset($_SESSION['username']);

// Destroy entire session
session_destroy();

// Check if session variable exists
if (isset($_SESSION['username'])) {
    echo $_SESSION['username'];
}
```

### Q40: How to create and use cookies?
**Answer:**
```php
// Create cookie (must be before output)
setcookie("username", "John", time() + (86400 * 30), "/");
// name, value, expiration (30 days), path

// Access cookie
echo $_COOKIE['username'];

// Delete cookie (set expiration to past)
setcookie("username", "", time() - 3600, "/");

// Check if cookie exists
if (isset($_COOKIE['username'])) {
    echo $_COOKIE['username'];
}

// Get all cookies
var_dump($_COOKIE);
```

### Q41: What is the difference between session_start() and session_regenerate_id()?
**Answer:**
```php
// session_start() - Starts or resumes session
session_start();

// session_regenerate_id() - Creates new session ID (prevents session fixation)
session_regenerate_id(true); // true = delete old session
```

---

## DATABASE AND SQL

### Q42: What are database normalization forms?
**Answer:**
1. **1NF (First Normal Form)**
   - Eliminate duplicate columns
   - Create separate tables for related data

2. **2NF (Second Normal Form)**
   - Meet 1NF requirements
   - Remove partial dependencies

3. **3NF (Third Normal Form)**
   - Meet 2NF requirements
   - Remove transitive dependencies

4. **BCNF (Boyce-Codd Normal Form)**
   - Stricter version of 3NF

### Q43: What is the difference between JOIN types?
**Answer:**
```sql
-- INNER JOIN (returns matching records)
SELECT * FROM table1 
INNER JOIN table2 ON table1.id = table2.id;

-- LEFT JOIN (returns all from left + matching from right)
SELECT * FROM table1 
LEFT JOIN table2 ON table1.id = table2.id;

-- RIGHT JOIN (returns all from right + matching from left)
SELECT * FROM table1 
RIGHT JOIN table2 ON table1.id = table2.id;

-- FULL OUTER JOIN (returns all from both)
SELECT * FROM table1 
FULL OUTER JOIN table2 ON table1.id = table2.id;

-- CROSS JOIN (Cartesian product)
SELECT * FROM table1 
CROSS JOIN table2;
```

### Q44: What is MySQLi and PDO?
**Answer:**
```php
// MySQLi (Procedural)
$conn = mysqli_connect("localhost", "user", "password", "db");
$result = mysqli_query($conn, "SELECT * FROM users");

// MySQLi (Object-oriented)
$conn = new mysqli("localhost", "user", "password", "db");
$result = $conn->query("SELECT * FROM users");

// PDO (PHP Data Objects)
$pdo = new PDO("mysql:host=localhost;dbname=db", "user", "password");
$result = $pdo->query("SELECT * FROM users");
```
- **MySQLi** - Only for MySQL
- **PDO** - Works with multiple databases

### Q45: What is SQL injection and how to prevent it?
**Answer:**
```php
// UNSAFE - SQL Injection vulnerable
$username = $_POST['username'];
$query = "SELECT * FROM users WHERE username = '$username'";

// SAFE - Using prepared statements
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $_POST['username']);
$stmt->execute();
$result = $stmt->get_result();

// SAFE - Using PDO
$pdo = new PDO("mysql:host=localhost;dbname=db", "user", "pass");
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$_POST['username']]);
```

### Q46: What are ACID properties?
**Answer:**
- **Atomicity** - Transaction is "all or nothing"
- **Consistency** - Database goes from one valid state to another
- **Isolation** - Transactions don't interfere with each other
- **Durability** - Committed data persists even after failure

```php
try {
    $pdo->beginTransaction();
    
    $pdo->query("UPDATE accounts SET balance = balance - 100 WHERE id = 1");
    $pdo->query("UPDATE accounts SET balance = balance + 100 WHERE id = 2");
    
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Transaction failed";
}
```

---

## SECURITY

### Q47: What are the common security vulnerabilities?
**Answer:**
1. **SQL Injection** - Malicious SQL code in input
2. **XSS (Cross-Site Scripting)** - Malicious scripts in HTML
3. **CSRF (Cross-Site Request Forgery)** - Unauthorized actions
4. **Path Traversal** - Accessing files outside intended directory
5. **Command Injection** - Executing system commands
6. **XXE (XML External Entity)** - Malicious XML parsing
7. **Insecure Deserialization** - Unsafe object reconstruction

### Q48: How to prevent XSS attacks?
**Answer:**
```php
// UNSAFE
echo $_GET['user_input'];

// SAFE - HTML escape
echo htmlspecialchars($_GET['user_input'], ENT_QUOTES, 'UTF-8');

// SAFE - Filter
$clean = filter_var($_GET['user_input'], FILTER_SANITIZE_STRING);
echo $clean;

// SAFE - In attributes
echo htmlspecialchars($_GET['input'], ENT_QUOTES, 'UTF-8');

// JavaScript context
echo json_encode($_GET['input']);
```

### Q49: How to prevent CSRF attacks?
**Answer:**
```php
// Generate token
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Display in form
echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';

// Verify token
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('CSRF token validation failed');
}
```

### Q50: What is password hashing?
**Answer:**
```php
// Hash password
$password = "MyPassword123";
$hashed = password_hash($password, PASSWORD_BCRYPT);
// Store $hashed in database

// Verify password
if (password_verify($_POST['password'], $hashed)) {
    echo "Password is correct";
} else {
    echo "Password is wrong";
}

// Check if rehash needed
if (password_needs_rehash($hashed, PASSWORD_BCRYPT)) {
    $hashed = password_hash($_POST['password'], PASSWORD_BCRYPT);
    // Update database
}
```

### Q51: What are security headers?
**Answer:**
```php
// Content Security Policy
header("Content-Security-Policy: default-src 'self'");

// X-Frame-Options (prevent clickjacking)
header("X-Frame-Options: SAMEORIGIN");

// X-Content-Type-Options
header("X-Content-Type-Options: nosniff");

// Strict-Transport-Security
header("Strict-Transport-Security: max-age=31536000");

// X-XSS-Protection
header("X-XSS-Protection: 1; mode=block");
```

---

## FILE HANDLING

### Q52: How to read files in PHP?
**Answer:**
```php
// Read entire file as string
$content = file_get_contents('file.txt');

// Read file as array
$lines = file('file.txt');

// Read file line by line
$handle = fopen('file.txt', 'r');
while (!feof($handle)) {
    $line = fgets($handle);
    echo $line;
}
fclose($handle);
```

### Q53: How to write files in PHP?
**Answer:**
```php
// Write to file (overwrite)
file_put_contents('file.txt', 'Hello World');

// Append to file
file_put_contents('file.txt', 'Hello World', FILE_APPEND);

// Write using fopen
$handle = fopen('file.txt', 'w');
fwrite($handle, 'Hello World');
fclose($handle);
```

### Q54: How to handle file uploads?
**Answer:**
```php
// HTML form
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="file">
    <button>Upload</button>
</form>

// PHP handling
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $file = $_FILES['file'];
    
    // Validate
    if ($file['error'] == 0) {
        $allowed = ['jpg', 'png', 'gif'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (in_array(strtolower($ext), $allowed)) {
            if ($file['size'] <= 1000000) { // 1MB
                move_uploaded_file($file['tmp_name'], 'uploads/' . $file['name']);
            }
        }
    }
}
```

### Q55: What is the difference between fopen modes?
**Answer:**
```php
// 'r' - Read only, pointer at beginning
// 'r+' - Read and write, pointer at beginning
// 'w' - Write only, truncate file or create
// 'w+' - Read and write, truncate or create
// 'a' - Write only, append to end
// 'a+' - Read and write, append or create
// 'x' - Write only, fail if file exists
// 'x+' - Read and write, fail if exists

$handle = fopen('file.txt', 'r');
```

---

## REGULAR EXPRESSIONS

### Q56: What are regular expressions in PHP?
**Answer:**
```php
// PCRE functions
preg_match();      // Find first match
preg_match_all();  // Find all matches
preg_replace();    // Replace matches
preg_split();      // Split string by pattern

// Example
if (preg_match('/^[a-z]+@[a-z]+\.[a-z]+$/', 'test@example.com')) {
    echo "Valid email";
}

// Validate email
if (preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', 'test@example.com')) {
    echo "Valid email";
}

// Validate phone
if (preg_match('/^[0-9]{10}$/', '1234567890')) {
    echo "Valid phone";
}

// Replace
$text = "Hello World";
echo preg_replace('/World/', 'PHP', $text); // Hello PHP

// Split
$array = preg_split('/,/', 'a,b,c');
// ["a", "b", "c"]
```

### Q57: What are common regex patterns?
**Answer:**
```php
// Email
/^[a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/

// Phone (10 digits)
/^[0-9]{10}$/

// URL
/^https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&//=]*)$/

// Date (YYYY-MM-DD)
/^\d{4}-\d{2}-\d{2}$/

// Alphabetic only
/^[a-zA-Z]+$/

// Alphanumeric
/^[a-zA-Z0-9]+$/

// Username (5-15 chars, alphanumeric and underscore)
/^[a-zA-Z0-9_]{5,15}$/

// Strong password (min 8 chars, uppercase, lowercase, digit, special)
/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/
```

---

## INTERMEDIATE CONCEPTS

### Q58: What is namespaces in PHP?
**Answer:**
```php
// Define namespace
namespace MyProject;

class User {
    public function test() {
        echo "User class";
    }
}

// Use namespace
$user = new MyProject\User();
$user->test();

// Import namespace
use MyProject\User;
$user = new User();

// Nested namespaces
namespace MyProject\Admin;
class Dashboard {}

use MyProject\Admin\Dashboard;
$dash = new Dashboard();
```

### Q59: What is autoloading in PHP?
**Answer:**
```php
// Manual autoloading
spl_autoload_register(function($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        include $file;
    }
});

$user = new User(); // Automatically loads User.php

// With namespaces
spl_autoload_register(function($class) {
    $namespace = explode('\\', $class);
    $file = __DIR__ . '/src/' . implode('/', $namespace) . '.php';
    if (file_exists($file)) {
        include $file;
    }
});
```

### Q60: What is traits in PHP?
**Answer:**
```php
// Define trait
trait Logger {
    public function log($msg) {
        echo "Log: " . $msg;
    }
}

// Use trait in class
class User {
    use Logger;
}

$user = new User();
$user->log("User created"); // Log: User created

// Multiple traits
class Admin {
    use Logger, Auth;
}

// Trait with properties
trait HasTimestamps {
    public $created_at;
    public $updated_at;
    
    public function setTimestamps() {
        $this->created_at = date('Y-m-d H:i:s');
        $this->updated_at = date('Y-m-d H:i:s');
    }
}
```

### Q61: What is the difference between include, require, include_once, and require_once?
**Answer:**
Already covered in Q4, but detailed:

| Statement | Behavior | Error |
|-----------|----------|-------|
| include | Includes file | Warning, continues |
| require | Requires file | Fatal error, stops |
| include_once | Includes once | Warning, continues |
| require_once | Requires once | Fatal error, stops |

### Q62: What is Composer?
**Answer:**
```php
// Composer is PHP package manager
// composer.json
{
    "name": "myproject/myapp",
    "require": {
        "monolog/monolog": "^2.0"
    }
}

// Install dependencies
// composer install

// Use autoloading
require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$log = new Logger('name');
$log->pushHandler(new StreamHandler('logs/app.log'));
$log->info('Application start');
```

---

## ADVANCED CONCEPTS

### Q63: What is dependency injection?
**Answer:**
```php
// Without DI
class UserRepository {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
}

// With DI (constructor injection)
class UserRepository {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
}

// Usage
$db = new Database();
$repo = new UserRepository($db);

// DI Container
class Container {
    private $bindings = [];
    
    public function bind($name, $callback) {
        $this->bindings[$name] = $callback;
    }
    
    public function resolve($name) {
        return $this->bindings[$name]($this);
    }
}

$container = new Container();
$container->bind('db', function() {
    return new Database();
});

$db = $container->resolve('db');
```

### Q64: What is middleware?
**Answer:**
```php
// Middleware interface
interface Middleware {
    public function handle($request, $next);
}

// Authentication middleware
class AuthMiddleware implements Middleware {
    public function handle($request, $next) {
        if (!isset($_SESSION['user'])) {
            die('Unauthorized');
        }
        return $next($request);
    }
}

// Logging middleware
class LoggingMiddleware implements Middleware {
    public function handle($request, $next) {
        echo "Request logged: " . $_SERVER['REQUEST_URI'];
        return $next($request);
    }
}

// Pipeline
class Pipeline {
    public function send($request) {
        // ... execute middlewares in order
    }
}
```

### Q65: What is the MVC pattern?
**Answer:**
```php
// Model - Data and business logic
class User {
    public function getUser($id) {
        // Query database
    }
    
    public function saveUser($data) {
        // Save to database
    }
}

// View - Display data
// user_view.php
<h1><?php echo $user->name; ?></h1>

// Controller - Handle requests
class UserController {
    public function show($id) {
        $user = new User();
        $userData = $user->getUser($id);
        include 'user_view.php';
    }
    
    public function store() {
        $user = new User();
        $user->saveUser($_POST);
        header('Location: /users');
    }
}
```

### Q66: What are design patterns in PHP?
**Answer:**
```php
// Singleton Pattern - Only one instance
class Database {
    private static $instance;
    
    private function __construct() {}
    
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

$db1 = Database::getInstance();
$db2 = Database::getInstance();
// $db1 === $db2 (same instance)

// Factory Pattern - Create objects
class DatabaseFactory {
    public static function create($type) {
        if ($type == 'mysql') {
            return new MySQLDatabase();
        } else if ($type == 'sqlite') {
            return new SQLiteDatabase();
        }
    }
}

$db = DatabaseFactory::create('mysql');

// Observer Pattern - Notify observers
class Subject {
    private $observers = [];
    
    public function attach($observer) {
        $this->observers[] = $observer;
    }
    
    public function notify() {
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }
}
```

### Q67: What is RESTful API?
**Answer:**
```php
// REST principles
// GET - Retrieve resource
// POST - Create resource
// PUT - Update resource
// DELETE - Delete resource

// Simple REST API
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($method) {
    case 'GET':
        if (strpos($path, '/users/') !== false) {
            $id = explode('/', $path)[2];
            echo json_encode(['id' => $id, 'name' => 'John']);
        }
        break;
    
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode(['status' => 'created', 'data' => $data]);
        break;
    
    case 'PUT':
        echo json_encode(['status' => 'updated']);
        break;
    
    case 'DELETE':
        echo json_encode(['status' => 'deleted']);
        break;
}
```

### Q68: What are generators in PHP?
**Answer:**
```php
// Generator function - uses yield
function numbers() {
    for ($i = 1; $i <= 5; $i++) {
        yield $i;
    }
}

foreach (numbers() as $num) {
    echo $num . " ";  // 1 2 3 4 5
}

// Generator with keys
function keyValue() {
    yield 'a' => 1;
    yield 'b' => 2;
    yield 'c' => 3;
}

foreach (keyValue() as $key => $value) {
    echo "$key: $value ";
}

// Generator for reading large files
function readLargeFile($file) {
    $handle = fopen($file, 'r');
    while (!feof($handle)) {
        yield fgets($handle);
    }
    fclose($handle);
}

foreach (readLargeFile('large.txt') as $line) {
    echo $line;
}
```

### Q69: What are closures and use keyword?
**Answer:**
```php
$name = "World";

// Closure with use
$greet = function() use ($name) {
    echo "Hello " . $name;
};

$greet(); // Hello World

// Pass by reference
$count = 0;
$increment = function() use (&$count) {
    $count++;
};

$increment();
$increment();
echo $count; // 2

// Arrow function (short closure) - PHP 7.4+
$add = fn($a, $b) => $a + $b;
echo $add(5, 3); // 8

// Arrow function with external variable
$x = 10;
$addX = fn($a) => $a + $x;
echo $addX(5); // 15
```

### Q70: What are type hints and return types?
**Answer:**
```php
// Type hints (parameter types)
function add(int $a, int $b): int {
    return $a + $b;
}

echo add(5, 3); // 8

// Nullable types
function getName(?string $name): ?string {
    return $name;
}

// Union types (PHP 8.0+)
function process(int|string $value): string {
    return (string)$value;
}

// Strict types
declare(strict_types=1);

// Array type
function getUsers(array $ids): array {
    return [];
}

// Mixed type (PHP 8.0+)
function handle(mixed $value): mixed {
    return $value;
}

// Void return type
function display(string $msg): void {
    echo $msg;
}
```

---

## Summary

This comprehensive guide covers:
- **70 theory questions** covering all PHP topics
- **Practical code examples** for each concept
- **Common interview questions** you'll likely encounter
- **Security best practices**
- **Advanced OOP concepts**
- **Database and SQL basics**
- **Design patterns**

**Study Tips:**
1. Understand concepts, don't just memorize
2. Practice code examples
3. Build small projects
4. Read PHP documentation
5. Follow security best practices

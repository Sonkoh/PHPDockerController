# PHPDockerController
Manage your Containers, Images & Volumes of Docker as Objects in PHP.

#### Requeriments 🍄

- PHP >= 8.1 📌
- Composer📌
- Docker Deamon 📌

#### Installation

- Install `PHPDockerController` with:
```sh
$ composer require sonkoh/php-docker-controller
```
- Create or modify a `.env`, else, modify the config file in `/vendor/sonkoh/php-docker-controller/Config.php` and set
```dotenv
DOCKER_VERSION=v1.25 # Your version of Deamon (you can get it with `docker --version`)
DOCKER_API="http://127.0.0.1:2378" # Your deamon api link
DOCKER_ENABLE="true"
```

#### Documentation

##### Errors

If the demon's response is not positive. The library will handle errors with:

```php
return throw new Exception("Error message");
```

To get error message you should use try {} catch.
```php
try {
	new Container(); // It will return an error because the constructor is not being used correctly.
} catch (Exception $e) {
    echo 'Error: ',  $e->getMessage(), "\n";
}
```

##### Create a container.

```php
$container = new Container("container_name", [
    "hostname" => "",
    "domainname" => "",
    "user" => "",
    "attachStdin" => false,
    "attachStdout" => true,
    "attachStderr" => true,
    "tty" => false,
    "openStdin" => false,
    "stdinOnce" => false,
    "envrioment" => [
        "FOO" => "bar",
        "BAZ" => "quux"
    ],
    "cmd" => [
        "echo",
        "Hello World"
    ],
    "entrypoint" => "",
    "image" => Image::find("ubuntu:latest"),
    "labels" => [
        "com.example.vendor" => "Acme",
        "com.example.license" => "GPL",
        "com.example.version" => "1.0"
    ],
    "volumes" => [
        "/root" => new Volume("volume_1"), // Use a volume
        "/home" => "/deamon/location/to" // Use a custom location
    ],
    "workingdir" => "",
    "networkdisabled" => false,
    "macaddress" => "",
    "ports" => [
        new Port("tcp", 80, "", 8080)
    ],
    "stopsignal" => "SIGTERM",
    "stoptimeout" => 10,
    "hostconfig" => [], // HostConfig data in `https://docs.docker.com/engine/api/v1.43/#tag/Container/operation/ContainerCreate`
    "networkingconfig" => (object) [] // NetworkingConfig in `https://docs.docker.com/engine/api/v1.43/#tag/Container/operation/ContainerCreate`. It most be an associative array.
]);

print($container); // Returns container id.
```

##### Container methods

```php
$container->rename("new_name"); // Rename a container
$container->update([]); // Update container data. Doc: `https://docs.docker.com/engine/api/v1.43/#tag/Container/operation/ContainerUpdate`
$container->view(); // Show low-level data about the container
$container->processes(); // Show processes runing inside the container.
$container->resizeTTY($width, $height); // Resize TTY for a container.
$container->logs(); // Get logs of the container.
$container->restart(); // Restart the container.
$container->start(); // Turn on the container.
$container->stop(); // Turn off the container.
$container->kill(); // Kill the container.
$container->pause(); // Pause the container.
$container->unpause(); // Unpause the container.
$container->remove(); // Remove the container.
$container->stats(); // Get container stats.
```

##### Container static methods

```php
Container::all(); // Get all containers in an array.
Container::find($container_id); // Get an container with id.
```

##### Create a port binding

```php
$port = new Port(
	"tcp", // Port type (tcp or udp).
    80, // Destination port.
    "", // Container IP. "" default.
    8080 // Container port
)
```

##### Get an existing image

```php
$image = Image::find("ubuntu:latest")
print($image); // Returns image identifier.
```

##### Build an image

```php
$image = new Image("image_name", "/context/location")
print($image); // Returns image identifier.
```

##### Get an existing volume

```php
$volume = Volume::find("volume_identifier")
print($volume); // Returns volume identifier.
```

##### Create a volume

```php
$volume = new Volume("volume_name") 
print($volume); // Returns volume identifier.
```

##### Remove an existing volume

```php
$volume = Volume::find("volume_name")
$volume->remove();
```

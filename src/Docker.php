<?php

namespace Sonkoh\Docker;

use Exception;

function ExecuteRequest($method, $api, $data = "{}")
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => Auth::GetConfig("DOCKER_API") . "/" . Auth::GetConfig("DOCKER_VERSION") . $api,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return [
        "code" => curl_getinfo($curl, CURLINFO_HTTP_CODE),
        "response" => $response
    ];
}

class Auth
{
    static function GetConfig($item)
    {
        return !empty(getenv($item)) ? getenv($item) : (require 'config.php')[$item];
    }

    static function Check()
    {
        if (strval(Auth::GetConfig("DOCKER_ENABLE")) != "true")
            return "\n-----------------------------------------------\n     📌 To use `php-docker-controller` 📌\n           You must have enabled:\n  `" . Auth::GetConfig("DOCKER_API") . "/" . Auth::GetConfig("DOCKER_VERSION") . "/containers/json`\n    Or change version with `DOCKER_VERSION`\n   and api with `DOCKER_API` in envrioment\n    Set `DOCKER_ENABLE=true` in envrioment\n          to disable this message\n-----------------------------------------------\n";
        if (empty(Auth::GetConfig("DOCKER_VERSION")))
            return "You must declare the envrioment variable `DOCKER_VERSION`.";
    }
}

class Container
{
    private $identifier;

    /**
     * Create a container.
     *
     * @return string Identifier for the container.
     * @param string $name Container name.
     * @param array $data Request Body Schema. Examples:
     * `https://docs.docker.com/engine/api/v1.43/#tag/Container/operation/ContainerCreate`
     */
    function __construct(String $name, $data = [])
    {
        if ($data == "findContainer%%u") {
            $this->identifier = $name;
            return;
        }
        if ($check = Auth::Check())
            return throw new Exception($check);
        if (!preg_match('/^[\/]?[a-zA-Z0-9][a-zA-Z0-9_.-]+$/', $name))
            return throw new Exception("Name must match regular expression: `/^[\/]?[a-zA-Z0-9][a-zA-Z0-9_.-]+$/`");
        $data["hostconfig"] = $data["hostconfig"] ?? [];
        $data["hostconfig"]["Binds"] = [];
        $env = [];
        foreach ($data["envrioment"] as $key => $val) {
            array_push($env, "$key=$val");
        }
        $ports = (object) [];
        $exposedPorts = (object) [];
        foreach ($data["ports"] as $port) {
            $exposedPorts->{strval($port->destination_port) . "/$port->port_type"} = (object) [];
            $ports->{strval($port->destination_port) . "/$port->port_type"} = [
                [
                    "HostIp" => $port->container_ip,
                    "HostPort" => strval($port->container_port)
                ]
            ];
        }
        $volumes = [];
        foreach ($data["volumes"] as $key => $val) {
            $volumes[] = "$val:$key";
        }
        $data["hostconfig"]["Binds"] = $volumes;
        $data["hostconfig"]["PortBindings"] = $ports;
        $container = [
            "Hostname" => $data["hostname"] ?? "",
            "Domainname" => $data["domainname"] ?? "",
            "User" => $data["user"] ?? "",
            "AttachStdin" => $data["attachStdin"] ?? false,
            "AttachStdout" => $data["attachStdout"] ?? true,
            "AttachStderr" => $data["attachStderr"] ?? true,
            "Tty" => $data["tty"] ?? false,
            "OpenStdin" => $data["openStdin"] ?? false,
            "StdinOnce" => $data["stdinOnce"] ?? false,
            "Env" => $env,
            "Cmd" => $data["cmd"] ?? ["/bin/bash"],
            "Entrypoint" => isset($data["entrypoint"]) ? $data["entrypoint"] : null,
            "Image" => $data["image"]->identifier,
            "Labels" => $data["labels"] ?? [],
            "Volumes" => (object) [],
            "WorkingDir" => $data["workingdir"] ?? "",
            "NetworkDisabled" => $data["networkdisabled"] ?? false,
            "MacAddress" => $data["macaddress"] ?? "",
            "ExposedPorts" => $exposedPorts,
            "StopSignal" => $data["stopsignal"] ?? "SIGTERM",
            "StopTimeout" => $data["stoptimeout"] ?? 10,
            "HostConfig" => $data["hostconfig"],
            "NetworkingConfig" => $data["networkingconfig"] ?? (object) []
        ];
        echo "\n";
        echo "\n";
        echo "\n";
        echo json_encode($container);
        echo "\n";
        echo "\n";
        echo "\n";
        echo "\n";
        $req = ExecuteRequest("POST", "/containers/create?name=" . $name, json_encode($container));
        echo $req["response"];
        echo "\n";
        echo "\n";
        if ($req["code"] != 201)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        $this->identifier = json_decode($req["response"], true)["Id"];
        return $this->identifier;
    }

    /**
     * Change various configuration options of a container without having to recreate it.
     *
     * @return bool
     * @param array $data Request Body Schema. Examples:
     * `https://docs.docker.com/engine/api/v1.43/#tag/Container/operation/ContainerUpdate`
     */
    function update($data = [])
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $data = $data == [] ? "{}" : json_encode($data);
        $req = ExecuteRequest("POST", "/containers/$this->identifier/update", $data);
        if ($req["code"] != 200)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return true;
    }

    /**
     * Rename container.
     *
     * @return array
     * @param string $name Container name.
     */
    public function rename($name)
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        if (!preg_match('/^[\/]?[a-zA-Z0-9][a-zA-Z0-9_.-]+$/', $name))
            return [
                "success" => false,
                "code" => 400,
                "message" => "Name must match regular expression: `/^[\/]?[a-zA-Z0-9][a-zA-Z0-9_.-]+$/`"
            ];
        $req = ExecuteRequest("POST", "/containers/$this->identifier/rename?name=" . $name);
        if ($req["code"] != 204) {
            return throw new Exception(json_decode($req["response"], true)["message"]);
        }
        return true;
    }

    /**
     * Returns low-level information about the container.
     *
     * @return array
     */


    public function view()
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("GET", "/containers/$this->identifier/json");
        if ($req["code"] != 200)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return json_decode($req["response"], true);
    }

    /**
     * Get processes runing inside the container.
     *
     * @return array
     * @param string $ps_args The arguments to pass to `ps`. For example, `aux`.
     */

    public function processes($ps_args = "-ef")
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("GET", "/containers/$this->identifier/json");
        if ($req["code"] != 200)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return json_decode($req["response"], true);
    }

    /**
     * Resize TTY for a container.
     *
     * @return array
     * @param integer $width Width of the TTY session in characters.
     * @param integer $height Height of the TTY session in characters.
     */

    public function resizeTTY($width, $height)
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("POST", "/containers/$this->identifier/resize?h=$height&w=$width");
        if ($req["code"] != 200)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return json_decode($req["response"], true);
    }

    /**
     * Get logs of the container.
     *
     * @return array
     * @param bool $follow Keep connection after returning logs.
     * @param bool $stdout Return logs from `stdout`
     * @param bool $stderr Return logs from `stderr`
     * @param integer $since Only return logs since this time, as a UNIX timestamp
     * @param integer $until Only return logs before this time, as a UNIX timestamp
     * @param bool $timestamps Add timestamps to every log line
     * @param string $tail Only return this number of log lines from the end of the logs. Specify as an integer or `all` to output all log lines.
     */

    public function logs($follow = false, $stdout = true, $stderr = false, $since = 0, $until = 0, $timestamps = false, $tail = "all")
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("GET", "/containers/$this->identifier/logs?follow=$follow&&stdout=$stdout&&stderr=$stderr&&since=$since&&until=$until&&timestamps=$timestamps&&tail=$tail");
        if ($req["code"] != 200)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return $req["response"];
    }

    /**
     * Turn on the container.
     *
     * @return bool
     */

    public function start()
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("POST", "/containers/$this->identifier/start");
        if ($req["code"] != 204)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return true;
    }

    /**
     * Turn off the container.
     *
     * @return bool
     */

    public function stop()
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("POST", "/containers/$this->identifier/stop");
        if ($req["code"] != 204)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return true;
    }

    /**
     * Restart the container.
     *
     * @return bool
     */

    public function restart()
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("POST", "/containers/$this->identifier/restart");
        if ($req["code"] != 204)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return true;
    }

    /**
     * Kill the container.
     *
     * @return bool
     */

    public function kill()
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("POST", "/containers/$this->identifier/kill");
        if ($req["code"] != 204)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return true;
    }

    /**
     * Pause the container.
     *
     * @return bool
     */

    public function pause()
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("POST", "/containers/$this->identifier/pause");
        if ($req["code"] != 204)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return true;
    }

    /**
     * Unpause the container.
     *
     * @return bool
     */

    public function unpause()
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("POST", "/containers/$this->identifier/unpause");
        if ($req["code"] != 204)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return true;
    }

    /**
     * Get processes runing inside the container.
     *
     * @return array
     * @param bool $stream Stream the output. If false, the stats will be output once and then it will disconnect.
     * @param bool $oneShot Only get a single stat instead of waiting for 2 cycles. Must be used with `$stream=false`.
     */

    public function stats($stream = false, $oneShot = true)
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("GET", "/containers/$this->identifier/stats?stream=$stream&&one-shot=$oneShot");
        if ($req["code"] != 200)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return json_decode($req["response"], true);
    }

    /**
     * Remove the container.
     *
     * @return bool
     * @param bool $v Remove anonymous volumes associated with the container.
     * @param bool $force If the container is running, kill it before removing it.
     * @param bool $link Remove the specified link associated with the container.
     */

    public function remove($v = false, $force = false, $link = false)
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("DELETE", "/containers/$this->identifier?v=$v&&force=$force&&link=$link");
        if ($req["code"] != 204)
            return throw new Exception($req["response"]["message"]);
        return json_decode($req["response"], true);
    }

    /**
     * Get all containers.
     *
     * @return array
     * @param bool $only_running Show only running containers.
     * @param integer $limit Return this number of most recently created containers, including non-running ones.
     * @param bool $size Return the size of container as fields `SizeRw` and `SizeRootFs`.
     * @param array $filters Filters to process on the container list. Available filters:
     * https://docs.docker.com/engine/api/v1.43/#tag/Container/operation/ContainerList
     */

    static function all($only_running = true, $limit = null, $size = false, $filters = [])
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $filters = $filters == [] ? "{}" : json_encode($filters);
        $only_running = !$only_running;
        $req = ExecuteRequest("GET", "/containers/json?all=$only_running&&limit=$limit&&size=$size&&filters=$filters");
        if ($req["code"] != 200)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return json_decode($req["response"], true);
    }

    /**
     * Get low-level information about a container.
     *
     * @return Container
     * @param string $identifier Container id
     */

    static function find($identifier)
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("GET", "/containers/$identifier/json");
        if ($req["code"] != 200)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return new Container($identifier, "findContainer%%u");
    }

    public function __toString()
    {
        return $this->identifier;
    }
}

class Port
{
    public $port_type, $destination_port, $container_ip, $container_port;
    /**
     * Create a port binding for a container.
     *
     * @param string $identifier Container id
     */
    function __construct(string $port_type = "tcp", int $destination_port, string $container_ip, int $container_port)
    {
        $this->port_type = $port_type;
        $this->destination_port = $destination_port;
        $this->container_ip = $container_ip;
        $this->container_port = $container_port;
        return;
    }
}

class Volume
{
    public $volume_name;
    /**
     * Create a new volume.
     *
     * @param string $volume_name Volume name.
     * @param bool $b Don't touch it.
     */
    function __construct(string $volume_name, $b = false)
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $this->volume_name = $volume_name;
        if ($b)
            return;
        $req = ExecuteRequest("POST", "/volumes/create", json_encode([
            "Name" => $volume_name
        ]));
        if ($req["code"] != 201)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return;
    }

    /**
     * Remove the volume.
     *
     * @return bool
     * @param bool $force Force the removal of the volume.
     */

    public function remove($force = false)
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("DELETE", "/volumes/$this->volume_name", [
            "force" => $force
        ]);
        if ($req["code"] != 204)
            return throw new Exception($req["response"]["message"]);
        return true;
    }

    function __toString()
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        return $this->volume_name;
    }

    /**
     * Get an existing volume by volume name.
     *
     * @param string $name Volume name.
     */
    static function find($name)
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("GET", "/volumes/$name");
        if ($req["code"] != 200)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return new Volume($name, true);
    }
}

class Image
{
    public $identifier;
    /**
     * Create or find an image.
     *
     * @param bool $b Keep it in false.
     * @param string $name Image name.
     * @param string $context_location
     */
    function __construct(bool $b = false, string $volume_name, string $context_location = "")
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $this->identifier = $volume_name;
        if ($b)
            return;
        // $req = ExecuteRequest("POST", "/build", json_encode([
        //     "t" => $volume_name
        // ]));
        // // if ($req["code"] != 200)
        // //     return throw new Exception(json_decode($req["response"], true)["message"]);
        // // return;
    }


    function __toString()
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        return $this->identifier;
    }

    /**
     * Remove the image.
     *
     * @return bool
     * @param bool $force Force the removal of the image.
     * @param bool $noprune Do not delete untagged parent images.
     */

    public function remove($force = false, $noprune = false)
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("DELETE", "/images/$this->identifier", [
            "force" => $force,
            "noprune" => $noprune
        ]);
        if ($req["code"] != 200)
            return throw new Exception($req["response"]["message"]);
        return true;
    }

    /**
     * Get an existing image by image name.
     *
     * @param string $name Image name.
     */
    static function find(string $name)
    {
        if ($check = Auth::Check())
            return throw new Exception($check);
        $req = ExecuteRequest("GET", "/images/$name/json");
        if ($req["code"] != 200)
            return throw new Exception(json_decode($req["response"], true)["message"]);
        return new Image(true, json_decode($req["response"], true)["Id"]);
    }
}
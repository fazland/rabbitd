# rabbitd

AMQP message processor daemon written in PHP

## Installation

You can download the latest release from the [release page on GitHub](https://github.com/fazland/rabbitd/releases).

It is recommended to place the executable phar into its own folder and than symlink it in `/usr/local/bin/rabbitd` or
another folder included in `$PATH`

## Usage

### Configuration

You must provide a configuration file for rabbitd. Example:

```yaml
log_file: /var/log/rabbitd.log     # Log filename
verbosity: very_verbose            # Log verbosity. Could be: quiet, normal, verbose, very_verbose or debug

pid_file: /var/run/rabbitd.pid
plugins_dir: /usr/local/rabbitd/plugins

# AMQP connection parameters
connections:
    default:
        hostname: localhost
        port: 5672
        username: guest
        password: guest

# AMQP queues configuration
queues:
    tasks:
        connection: default        # Connection name
        queue_name: task_queue     # Queue name
        exchange:                  # Connect this queue to an exchange
            name: exchange_1
            type: fanout
        worker:
            processes: 1           # Process count per queue
            user: nobody           # Change worker process user (requires master to be executed as root)
            group: nogroup         # Change worker process group (requires master to be executed as root)

# Master execution options
master:
    user: root                     # Master process user
    group: nogroup                 # Master process group

```

When configured, it is recommended to execute the update dependencies command in order to update the daemon 
and the plugins libraries and dependencies.

```bash
$ rabbitd update-deps --config=/etc/rabbitd.yml
```

### Plugins

In order to handle messages you have to write your own plugin.
You can find more about this in its own documentation (todo).

# Run

```bash
$ rabbitd --config=/etc/rabbitd.yml
```

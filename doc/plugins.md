# Plugins

Plugins should implement the main message handling logic.

## Anatomy of a plugin

The plugin must have a main class in its root directory implementing `Fazland\Rabbitd\Plugin\PluginInterface`.  
You can simply extend `Fazland\Rabbitd\Plugin\AbstractPlugin` to start implementing the core logic.

A plugin must have a unique name which is returned by the `getName` method.



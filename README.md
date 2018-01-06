# EntityManager
  
Development: **[Leinne](https://github.com/Leinne)** (before milk0417)
  
EntityManager is a plugin for managing entities(Mob, Animal).  

## Start developing again!
I decided to **start development again**.  
I want lots of **issues** and **interests**.
  
## Notice

### Supported Server software
[PocketMine-MP](https://pmmp.io/)

### Dependency
This plug-in requires [PureEntities](https://github.com/LeinneSW/PureEntities) that support Entity
  
## YAML data
  * config.yml
``` yml
entity:
  not-spawn: [] #List of spawn prohibited entities
  explodeMode: false #Entity Explosion Mode(none, onlyEntity, cancelled)
autoclear:
  turn-on: true #Turn on / off automatic removal of entities
  tick: 6000 #Entity removal period(20 = 1second)
  entities: [Projectile, DroppedItem] #List of entities to remove
```
  
## Commands
| Command | Permission | Description |
| ----- | :-----: | ----- |
| `/entitymanager <check/remove/spawn>` | `entitymanager.command` |  |
| `/entitymanager check <LevelName>`| `entitymanager.command.check` | Check the number of entities(If blank, it is set as a default Level)|
| `/entitymanager remove <LevelName>`| `entitymanager.command.remove` | Remove all entities in Level(If blank, it is set as a default Level) |
| `/entitymanager spawn type <x y z Level>` | `entitymanager.command.spawn`| If blank, it is set as a Sender's Position|

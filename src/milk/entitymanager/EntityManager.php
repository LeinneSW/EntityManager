<?php

namespace milk\entitymanager;

use milk\entitymanager\task\AutoClearTask;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\entity\Item as ItemEntity;

class EntityManager extends PluginBase implements Listener{

    public static $data;
    public static $drops;

    public static function clear($type = ['Item', 'Projectile'], $level = \null){
        if($level === \null){
            $level = Server::getInstance()->getDefaultLevel();
        }

        if(!($level instanceof Level)){
            $level = Server::getInstance()->getLevelByName($level);
            if($level === \null){
                return;
            }
        }

        foreach($level->getEntities() as $entity){
            if($entity instanceof Player) continue;

            $reflect = new \ReflectionClass(\get_class($entity));
            while(\true){
                if(in_array($reflect->getShortName(), $type)){
                    $entity->flagForDespawn();
                    break;
                }

                if($reflect->getShortName() === 'Entity' || ($reflect = $reflect->getParentClass()) === \false){
                    break;
                }
            }
        }
    }

    /**
     * @param string $name
     * @param Item $item
     * @param int $minCount
     * @param int $maxCount
     */
    public static function addEntityDropItem($name, Item $item, $minCount, $maxCount){
        $list = EntityManager::$drops[$name] ?? [];

        foreach($list as $key => $data){
            if(($data[0] ?? 0) === $item->getId() && ($data[1] ?? 0) === $item->getDamage()){
                $data[2] = "$minCount,$maxCount";

                EntityManager::$drops[$name][$key] = $data;
                return;
            }
        }

        EntityManager::$drops[$name][] = [
            $item->getId(),
            $item->getDamage(),
            $minCount,
            $maxCount,
        ];
    }

    /**
     * @param string $name
     * @param Item $item
     */
    public static function removeEntityDropItem($name, Item $item){
        $list = EntityManager::$drops[$name] ?? [];

        foreach($list as $key => $data){
            if(($data[0] ?? 0) === $item->getId() && ($data[1] ?? 0) === $item->getDamage()){
                unset(EntityManager::$drops[$name][$key]);
                return;
            }
        }
    }

    /**
     * @param string $name
     */
    public static function resetEntityDropItem($name){
        unset(EntityManager::$drops[$name]);
    }

    public function onEnable(){
        $this->saveDefaultConfig();
        self::$data = $this->getConfig()->getAll();
        self::$drops = (new Config(self::getDataFolder() . "drops.yml", Config::YAML))->getAll();

        /*Drops Example
        Zombie:
          #id, data, count
          [288, 0, 1, 10],
          [392, 0, 1, 10]
        PigZombie:
          [266, 0, 0 ,8]
        */

        if(self::getData("autoclear.turn-on", \true)){
            $this->getServer()->getScheduler()->scheduleRepeatingTask(new AutoClearTask($this), self::getData("autoclear.tick", self::getData("autoclear.tick", 6000)));
        }

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getLogger()->info(TextFormat::GOLD . "[EntityManager]Plugin has been enabled");
    }

    public function onDisable(){
        $conf = new Config(self::getDataFolder() . "drops.yml", Config::YAML);
        $conf->setAll(EntityManager::$drops);
        $conf->save();

        $this->getServer()->getLogger()->info(TextFormat::GOLD . "[EntityManager]Plugin has been disable");
    }

    public static function getData(string $key, $defaultValue){
        $vars = explode(".", $key);
        $base = array_shift($vars);
        if(!isset(self::$data[$base])){
            return $defaultValue;
        }

        $base = self::$data[$base];
        while(count($vars) > 0){
            $baseKey = array_shift($vars);
            if(!is_array($base) or !isset($base[$baseKey])){
                return $defaultValue;
            }
            $base = $base[$baseKey];
        }
        return $base;
    }

    public function onEntitySpawnEvent(EntitySpawnEvent $ev){
        if(($entity = $ev->getEntity()) instanceof Player) return;

        $list = self::getData("entity.not-spawn", []);

        $reflect = new \ReflectionClass(\get_class($entity));
        while(\true){
            if(in_array($reflect->getShortName(), $list)){
                $entity->flagForDespawn();
                break;
            }

            if($reflect->getShortName() === 'Entity' || ($reflect = $reflect->getParentClass()) === \false){
                break;
            }
        }
    }

    public function ExplosionPrimeEvent(ExplosionPrimeEvent $ev){
        switch(self::getData("entity.explodeMode", "none")){
            case "onlyEntity":
                $ev->setBlockBreaking(false);
                break;
            case "none":
                $ev->setForce(0.00001);
                $ev->setBlockBreaking(false);
                break;
            case "cancelled":
                $ev->setCancelled();
                break;
        }
    }

    public function EntityDeathEvent(EntityDeathEvent $ev){
        $reflect = new \ReflectionClass(\get_class($ev->getEntity()));
        if(!isset(self::$drops[$reflect->getShortName()])){
            return;
        }

        $drops = [];
        foreach(self::$drops[$reflect->getShortName()] as $key => $data){
            if(!isset($data[0]) || !isset($data[1]) || !isset($data[2])){
                unset(self::$drops[$reflect->getShortName()][$key]);
                continue;
            }

            $item = Item::get($data[0] ?? 0, $data[1] ?? 0);
            if(!$item->setCount(\max(\mt_rand($data[2] ?? 0, $data[3] ?? 1), 0))->isNull()) $drops[] = $item;
        }
        $ev->setDrops($drops);
    }

    public function onCommand(CommandSender $i, Command $cmd, string $label, array $sub) : bool{
        $output = "§b§o[EntityManager]§7";
        switch(\array_shift($sub)){
            case "remove":
                if(!$i->hasPermission("entitymanager.command.remove")){
                    $i->sendMessage(TextFormat::RED . "You do not have permission to use this command");
                    return true;
                }

                if(isset($sub[0])){
                    $level = $this->getServer()->getLevelByName($sub[0]);
                }else{
                    $level = $i instanceof Player ? $i->getLevel() : null;
                }

                self::clear(self::getData('command.remove', ['Projectile', 'Item']), $level);
                $output .= "All spawned entities were removed";
                break;
            case "check":
                if(!$i->hasPermission("entitymanager.command.check")){
                    $i->sendMessage(TextFormat::RED . "You do not have permission to use this command");
                    return true;
                }

                $human = 0;
                $living = 0;
                $item = 0;
                $projectile = 0;
                $other = 0;
                if(isset($sub[0])){
                    $level = $this->getServer()->getLevelByName($sub[0]);
                }else{
                    $level = $i instanceof Player ? $i->getLevel() : $this->getServer()->getDefaultLevel();
                }

                foreach($level->getEntities() as $id => $ent) {
                    if($ent instanceof Human){
                        $human++;
                    }elseif($ent instanceof Living){
                        $living++;
                    }elseif($ent instanceof ItemEntity){
                        $item++;
                    }elseif($ent instanceof Projectile){
                        $projectile++;
                    }else{
                        $other++;
                    }
                }

                $output = "--- All entities in Level \"{$level->getName()}\" ---\n";
                $output .= TextFormat::YELLOW . "Human: $human\n";
                $output .= TextFormat::YELLOW . "Living: $living\n";
                $output .= TextFormat::YELLOW . "Items: $item\n";
                $output .= TextFormat::YELLOW . "Projectiles: $projectile\n";
                $output .= TextFormat::YELLOW . "Others: $other\n";
                break;
            case "create":
                if(!$i->hasPermission("entitymanager.command.create")){
                    $i->sendMessage(TextFormat::RED . "You do not have permission to use this command");
                    return true;
                }

                if(!isset($sub[0]) or (!\is_numeric($sub[0]) and \gettype($sub[0]) !== "string")){
                    $output .= "Entity's name is incorrect";
                    break;
                }

                $pos = \null;
                if(\count($sub) >= 4){
                    $level = $this->getServer()->getDefaultLevel();
                    if(isset($sub[4]) && ($k = $this->getServer()->getLevelByName($sub[4]))){
                        $level = $k;
                    }elseif($i instanceof Player){
                        $level = $i->getLevel();
                    }
                    $pos = new Position($sub[1], $sub[2], $sub[3], $level);
                }elseif($i instanceof Player){
                    $pos = $i->getPosition();
                }

                if($pos === \null){
                    $output .= "usage: /$label create <id/name> (x) (y) (z) (level)";
                    break;
                }

                $entity = Entity::createEntity($sub[0], $pos->level, Entity::createBaseNBT($pos));
                if($entity === \null){
                    $output .= "Entity name is incorrect";
                    break;
                }
                $output .= "Successfully created";
                $entity->spawnToAll();
                break;
            default:
                $output .= "usage: /$label <remove/check/create>";
                break;
        }
        $i->sendMessage($output);
        return true;
    }

}

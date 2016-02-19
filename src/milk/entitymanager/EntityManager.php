<?php

namespace milk\entitymanager;

use milk\entitymanager\task\AutoClearTask;
use milk\entitymanager\task\AutoSpawnTask;
use milk\pureentities\entity\animal\walking\Chicken;
use milk\pureentities\entity\animal\walking\Cow;
use milk\pureentities\entity\animal\walking\Mooshroom;
use milk\pureentities\entity\animal\walking\Ocelot;
use milk\pureentities\entity\animal\walking\Pig;
use milk\pureentities\entity\animal\walking\Rabbit;
use milk\pureentities\entity\animal\walking\Sheep;
use milk\pureentities\entity\BaseEntity;
use milk\pureentities\entity\monster\flying\Blaze;
use milk\pureentities\entity\monster\flying\Ghast;
use milk\pureentities\entity\monster\walking\CaveSpider;
use milk\pureentities\entity\monster\walking\Creeper;
use milk\pureentities\entity\monster\walking\Enderman;
use milk\pureentities\entity\monster\walking\IronGolem;
use milk\pureentities\entity\monster\walking\PigZombie;
use milk\pureentities\entity\monster\walking\Silverfish;
use milk\pureentities\entity\monster\walking\Skeleton;
use milk\pureentities\entity\monster\walking\SnowGolem;
use milk\pureentities\entity\monster\walking\Spider;
use milk\pureentities\entity\monster\walking\Wolf;
use milk\pureentities\entity\monster\walking\Zombie;
use milk\pureentities\entity\monster\walking\ZombieVillager;
use milk\pureentities\entity\projectile\FireBall;
use milk\pureentities\PureEntities;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Living;
use pocketmine\entity\Projectile;
use pocketmine\event\entity\EntityDeathEvent;
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
    public static $spawner;

    public function __construct(){
        $classes = [
            Blaze::class,
            CaveSpider::class,
            Chicken::class,
            Cow::class,
            Creeper::class,
            Enderman::class,
            Ghast::class,
            IronGolem::class,
            //MagmaCube::class,
            Mooshroom::class,
            Ocelot::class,
            Pig::class,
            PigZombie::class,
            Rabbit::class,
            Sheep::class,
            Silverfish::class,
            Skeleton::class,
            //Slime::class,
            SnowGolem::class,
            Spider::class,
            Wolf::class,
            Zombie::class,
            ZombieVillager::class,
            FireBall::class
        ];
        foreach($classes as $name){
            Entity::registerEntity($name);
            if(
                $name == IronGolem::class
                || $name == FireBall::class
                || $name == SnowGolem::class
                || $name == ZombieVillager::class
            ){
                continue;
            }
            $item = Item::get(Item::SPAWN_EGG, $name::NETWORK_ID);
            if(!Item::isCreativeItem($item)){
                Item::addCreativeItem($item);
            }
        }
    }

    public function onEnable(){
        $this->saveDefaultConfig();
        if($this->getConfig()->exists("spawn")){
            $this->saveResource("config.yml", true);
            $this->reloadConfig();
            $this->getServer()->getLogger()->info(TextFormat::GOLD . "[EntityManager]Your config has been updated. Please check \"config.yml\" file and restart the server.");
        }elseif($this->getConfig()->exists("spawner")){
            $this->getConfig()->remove("spawner");
            $this->getConfig()->save();
        }

        self::$data = $this->getConfig()->getAll();
        self::$drops = (new Config($this->getDataFolder() . "drops.yml", Config::YAML))->getAll();

        if(isset(self::$data["entity"]["explode"])){
            self::$data["entity"]["explodeMode"] = "none";
            unset(self::$data["entity"]["explode"]);
        }

        /*Drops Example
        Zombie:
          #id  meta count
          [288, 0, "1,10"],
          [392, 0, "1,10"]
        PigZombie:
          [266, 0, "0,8"]
        */

        if($this->getData("autospawn.turn-on", true)){
            $this->getServer()->getScheduler()->scheduleRepeatingTask(new AutoSpawnTask($this), $this->getData("autospawn.tick", 100));
        }
        if($this->getData("autoclear.turn-on", true)){
            $this->getServer()->getScheduler()->scheduleRepeatingTask(new AutoClearTask($this), $this->getData("autoclear.tick", $this->getData("autoclear.tick", 6000)));
        }

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getLogger()->info(TextFormat::GOLD . "[EntityManager]Plugin has been enabled");
    }

    public function onDisable(){
        $this->getServer()->getLogger()->info(TextFormat::GOLD . "[EntityManager]Plugin has been disable");
    }

    public static function clear(array $type = [BaseEntity::class], Level $level = null){
        $level = $level === null ? Server::getInstance()->getDefaultLevel() : $level;
        foreach($level->getEntities() as $id => $ent){
            foreach($type as $t){
                if(is_a($ent, $t, true)){
                    $ent->close();
                    continue;
                }
            }
        }
    }

    public function getData(string $key, $defaultValue){
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

    public function ExplosionPrimeEvent(ExplosionPrimeEvent $ev){
        switch($this->getData("entity.explodeMode", "none")){
            case "onlyEntity":
                $ev->setBlockBreaking(false);
                break;
            case "none":
                $ev->setForce(0);
                $ev->setBlockBreaking(false);
                break;
        }
    }

    public function EntityDeathEvent(EntityDeathEvent $ev){
        $reflect = new \ReflectionClass(get_class($ev->getEntity()));
        if(!isset(self::$drops[$reflect->getShortName()])){
            return;
        }

        $drops = [];
        foreach(self::$drops[$reflect->getShortName()] as $key => $data){
            if(!isset($data[0]) || !isset($data[1]) || !isset($data[2])){
                unset(self::$drops[$reflect->getShortName()][$key]);
                continue;
            }

            $count = explode(",", $data[2]);
            $item = Item::get($data[0], $data[1]);
            $item->setCount(max(mt_rand(...$count), 0));
            $drops[] = $item;
        }
        $ev->setDrops($drops);
    }

    public function onCommand(CommandSender $i, Command $cmd, $label, array $sub){
        $output = "[EntityManager]";
        switch(array_shift($sub)){
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

                self::clear([BaseEntity::class, Projectile::class, ItemEntity::class], $level);
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

                if(!isset($sub[0]) or (!is_numeric($sub[0]) and gettype($sub[0]) !== "string")){
                    $output .= "Entity's name is incorrect";
                    break;
                }

                $pos = null;
                if(count($sub) >= 4){
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

                if($pos == null){
                    $output .= "usage: /$label create <id/name> (x) (y) (z) (level)";
                    break;
                }

                $entity = PureEntities::create($sub[0], $pos);
                if($entity == null){
                    $output .= "An error occurred while summoning entity";
                    break;
                }
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
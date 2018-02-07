<?php

namespace milk\entitymanager\task;

use milk\entitymanager\EntityManager;
use pocketmine\Player;
use pocketmine\scheduler\PluginTask;
use pocketmine\Server;

class AutoClearTask extends PluginTask{

    public function onRun(int $currentTick){
        $levelList = [];
        $levels = EntityManager::getData('autoclear.levels', []);
        if(count($levels) > 0){
            foreach($levels as $levelname){
                $level = Server::getInstance()->getLevelByName($levelname);
                if($level !== null) $levelList[] = $level;
            }
        }

        $type = EntityManager::getData('autoclear.entities', ['Projectile', 'Item']);
        foreach((count($levelList) > 0 ? $levelList : Server::getInstance()->getLevels()) as $level){
            foreach($level->getEntities() as $entity){
                EntityManager::closeEntityByClassName($type, $entity);
            }
        }
    }

}

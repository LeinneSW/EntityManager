<?php

declare(strict_types=1);

namespace milk\entitymanager\task;

use milk\entitymanager\EntityManager;
use pocketmine\scheduler\Task;
use pocketmine\Server;

class AutoClearTask extends Task{

    public function onRun(int $currentTick) : void{
        $levelList = [];
        $levels = EntityManager::getData('autoclear.levels', []);
        if(count($levels) > 0){
            foreach($levels as $k => $levelname){
                $level = Server::getInstance()->getLevelManager()->getLevelByName($levelname);
                if($level !== null) $levelList[] = $level;
            }
        }

        $type = EntityManager::getData('autoclear.entities', ['Projectile', 'Item']);
        foreach((\count($levelList) > 0 ? $levelList : Server::getInstance()->getLevelManager()->getLevels()) as $k => $level){
            foreach($level->getEntities() as $id => $entity){
                EntityManager::despawnEntityByClass($type, $entity);
            }
        }
    }

}

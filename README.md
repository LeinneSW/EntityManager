# EntityManager
  
Author(제작자): **[SW승원(milk0417)](https://github.com/milk0417)**  
  
자매품(Nukkit): [EntityManager-Nukkit](https://github.com/SW-Team/EntityManager)
  
EntityManager is a plugin for managing entities(Mob, Animal), literally.  
This plug-in requires [PureEntities](https://github.com/milk0417/PureEntities) that support Entity
    
EntityManager는 말 그대로 Entity(Mob, Animal)를 관리하는 플러그인을 의미합니다.  
이 플러그인은 Entity를 Nukkit에서 구현시켜주는 [PureEntities](https://github.com/milk0417/PureEntities) 플러그인이 필요합니다.
  
### YAML data
  * config.yml
``` yml
entity:
  explodeMode: false #엔티티 폭발모드(none, onlyEntity)
autospawn:
  turn-on: true #자동 소환 활성화 여부
  rand: "1/4" #엔티티 스폰 확률
  radius: 25 #몹이 스폰될 최대 반경
  tick: 100 #스폰 주기(20 = 1second)
  entities:
    animal: [Cow, Pig, Sheep, Chicken, Slime, Wolf, Ocelot, Rabbit] #소환될 동물 목록
    monster: [Zombie, Creeper, Skeleton, Spider, PigZombie, Enderman] #소환될 몬스터 목록
autoclear:
  turn-on: true #엔티티 자동 제거 여부
  tick: 6000 #엔티티 제거 주기(20 = 1second)
  entities: [Projectile, DroppedItem] #제거될 엔티티 목록
```
  * drops.yml
    * TODO
  
### Commands(명령어)
  * /entitymanager
    * usage: /entitymanager (check|remove|spawn)
    * permission: entitymanager.command
  * /entitymanager check
    * usage: /entitymanager check (Level="")
    * permission: entitymanager.command.check
    * description: Check the number of entities(If blank, it is set as a default Level)
  * /entitymanager remove
    * usage: /entitymanager remove (Level="")
    * permission: entitymanager.command.remove
    * description: Remove all entities in Level(If blank, it is set as a default Level)
  * /entitymanager spawn:
    * usage: /entitymanager spawn (type) (x="") (y="") (z="") (Level="")
    * permission: entitymanager.command.spawn
    * description: literally(If blank, it is set as a Player)
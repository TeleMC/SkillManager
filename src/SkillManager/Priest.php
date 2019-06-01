<?php
namespace SkillManager;

use Monster\mob\MonsterBase;
use Monster\mob\PersonBase;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\HugeExplodeSeedParticle;
use pocketmine\level\particle\PortalParticle;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;
use pocketmine\scheduler\Task;

class Priest {
    public function __construct(SkillManager $plugin) {
        $this->plugin = $plugin;
    }

    public function Active_1_Particle_2(Entity $target) {
        $target->level->addParticle(new HugeExplodeSeedParticle($target));
        $target->getLevel()->broadcastLevelSoundEvent($target, LevelSoundEventPacket::SOUND_EXPLODE);
        $target->getLevel()->broadcastLevelSoundEvent($target, LevelSoundEventPacket::SOUND_EXPLODE);
        $target->getLevel()->broadcastLevelSoundEvent($target, LevelSoundEventPacket::SOUND_EXPLODE);
    }

    public function Active_1(Player $player) {
        $SkillName = "퓨어힐";
        if (!$this->plugin->isSkill($player->getName(), $SkillName))
            return false;
        if ($this->plugin->getSkillLevel($player->getName(), $SkillName) <= 0)
            return false;
        if ($this->plugin->equipments->getEquipmentType($player->getEnderChestInventory()->getItem(0)->getCustomName()) !== "위자드") { // 위자드와 프리스트 무기 동일
            //$player->sendPopup("{$this->plugin->pre} 스태프를 들고있지 않습니다!");
            $this->plugin->sendPopupDelay($player, "스태프를 들고있지 않습니다!");
            return false;
        }
        if (isset($this->plugin->Wizard_Active_1[$player->getId()]) && !$this->plugin->Wizard_Active_1[$player->getId()]->isClosed())
            return false;
        $SkillLevel = $this->plugin->getSkillLevel($player->getName(), $SkillName);
        $SkillMana = $this->plugin->getSkillMana($SkillName, $SkillLevel);
        if ($this->plugin->util->getMp($player->getName()) < $this->plugin->getSkillMana($SkillName, $SkillLevel)) {
            //$player->sendPopup("{$this->plugin->pre} 마나가 부족합니다.");
            $this->plugin->sendPopupDelay($player, "마나가 부족합니다.");
            return false;
        }
        $this->plugin->util->reduceMp($player->getName(), $SkillMana);
        $this->plugin->ability->addBornPoint($player->getName(), "리커버리", 1);
        $this->plugin->ability->addBornPoint($player->getName(), "성력", 1);
        $this->Active_1_Particle($player);
        $heal = explode(":", $this->plugin->getSkillDamage($SkillName, $SkillLevel));
        $heal = ($heal[0] * ($this->plugin->util->getMATK($player->getName()) + $this->plugin->equipments->getMATK($player)) + $heal[1]) * 2;
        if ($this->plugin->isSkill($player->getName(), "프리스트의 집중") && $this->plugin->getSkillLevel($player->getName(), "프리스트의 집중") > 0)
            $heal *= (1 + ($this->plugin->getSkillInfo("프리스트의 집중", $this->plugin->getSkillLevel($player->getName(), "프리스트의 집중"))));
        foreach ($player->level->getNearbyEntities($player->boundingBox->expandedCopy(15, 15, 15), $player) as $target) {
            if ($target instanceof Player && $this->plugin->party->isParty($player->getName()) && $this->plugin->party->isParty($target->getName()) && $this->plugin->party->getParty($player->getName()) == $this->plugin->party->getParty($target->getName())) {
                $player->heal(new EntityRegainHealthEvent($target, $heal, 3));
                $target->sendPopup("{$this->plugin->pre} 프리스트, {$player->getName()}님의 스킬로 체력이 회복되었습니다.");
            }
        }
        $player->heal(new EntityRegainHealthEvent($player, $heal, 3));
        //$player->sendPopup("{$this->plugin->pre} 스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        $this->plugin->sendPopupDelay($player, "스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        return true;
    }

    public function Active_1_Particle(Player $player) {
        $diff = 0.5;
        $r = 15;
        for ($y = 0.2; $y <= 1; $y += 0.2) {
            for ($theta = 0; $theta <= 360; $theta += $diff) {
                if ($y >= 1)
                    return false;
                $x = $r * sin($theta);
                $z = $r * cos($theta);
                $player->getLevel()->addParticle(new DustParticle($player->add($x, $y, $z), 103, 152, 253));
            }
        }
    }

    public function Active_2(Player $player) {
        $SkillName = "천벌";
        if (!$this->plugin->isSkill($player->getName(), $SkillName))
            return false;
        if ($this->plugin->getSkillLevel($player->getName(), $SkillName) <= 0)
            return false;
        if ($this->plugin->equipments->getEquipmentType($player->getEnderChestInventory()->getItem(0)->getCustomName()) !== "위자드") { // 위자드와 프리스트 무기 동일
            //$player->sendPopup("{$this->plugin->pre} 스태프를 들고있지 않습니다!");
            $this->plugin->sendPopupDelay($player, "스태프를 들고있지 않습니다!");
            return false;
        }
        $SkillLevel = $this->plugin->getSkillLevel($player->getName(), $SkillName);
        $SkillMana = $this->plugin->util->getMaxMp($player->getName()) * ($this->plugin->getSkillMana($SkillName, $SkillLevel)) / 100;
        if ($this->plugin->util->getMp($player->getName()) < $SkillMana) {
            //$player->sendPopup("{$this->plugin->pre} 마나가 부족합니다.");
            $this->plugin->sendPopupDelay($player, "마나가 부족합니다.");
            return false;
        }
        $this->plugin->util->reduceMp($player->getName(), $SkillMana);
        $this->plugin->ability->addBornPoint($player->getName(), "마력 제어", 1);
        $this->plugin->ability->addBornPoint($player->getName(), "마력 방출", 1);
        //$player->sendPopup("{$this->plugin->pre} 스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        $this->plugin->Priest_Active_2[$player->getId()] = [];
        $count = 0;
        foreach ($player->level->getNearbyEntities(new AxisAlignedBB($player->x - 9, $player->y - 9, $player->z - 9, $player->x + 9, $player->y + 9, $player->z + 9)) as $target) {
            if (3 <= $count) break;
            if ($target instanceof MonsterBase || $target instanceof PersonBase) {
                $this->plugin->Priest_Active_2[$player->getId()][$target->getId()] = $target;
                $source = new EntityDamageByEntityEvent($player, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 0);
                $target->attack($source);
                $count++;
            }
        }
        $this->plugin->sendPopupDelay($player, "스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        return true;
    }
}

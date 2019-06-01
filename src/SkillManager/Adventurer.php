<?php
namespace SkillManager;

use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;
use pocketmine\scheduler\Task;

class Adventurer {
    public function __construct(SkillManager $plugin) {
        $this->plugin = $plugin;
    }

    public function Passive_1(Player $player) {
        $SkillName = "산뜻한 발걸음";
        if (!$this->plugin->isSkill($player->getName(), $SkillName))
            return false;
        if ($this->plugin->getSkillLevel($player->getName(), $SkillName) <= 0)
            return false;
        if ($this->plugin->isUsingSkill($player->getName(), $SkillName)) {
            //$player->sendPopup("{$this->plugin->pre} 이미 시전중입니다.");
            $this->plugin->sendPopupDelay($player, "이미 시전중입니다.");
            return false;
        }
        $SkillLevel = $this->plugin->getSkillLevel($player->getName(), $SkillName);
        $SkillMana = $this->plugin->getSkillMana($SkillName, $SkillLevel);
        if ($this->plugin->util->getMp($player->getName()) < $this->plugin->getSkillMana($SkillName, $SkillLevel)) {
            //$player->sendPopup("{$this->plugin->pre} 마나가 부족합니다.");
            $this->plugin->sendPopupDelay($player, "마나가 부족합니다.");
            return false;
        }
        $SkillTime = $this->plugin->getSkillTime($SkillName, $SkillLevel);
        $SkillEffect = $this->plugin->getSkillEffect($SkillName, $SkillLevel);
        $SkillEffectPower = $this->plugin->getSkillEffectPower($SkillName, $SkillLevel);
        $this->plugin->util->reduceMp($player->getName(), $SkillMana);
        $this->plugin->SkillSound($player, 232);
        $this->plugin->useSkill($player->getName(), $SkillName);
        $instance = new EffectInstance(Effect::getEffect(Effect::SPEED), $SkillTime * 20, $SkillEffectPower - 1, true);
        $player->addEffect($instance);
        $this->plugin->getScheduler()->scheduleDelayedTask(
                new class($this->plugin, $player, $SkillName, $this->plugin->list[$player->getName()][$SkillName]) extends Task {
                    public function __construct(SkillManager $plugin, Player $player, string $SkillName, int $count) {
                        $this->plugin = $plugin;
                        $this->player = $player;
                        $this->skill = $SkillName;
                        $this->count = $count;
                    }

                    public function onRun($currentTick) {
                        if ($this->player instanceof Player && $this->plugin->isUsingSkill($this->player->getName(), $this->skill) && $this->count == $this->plugin->list[$this->player->getName()][$this->skill]) {
                            $this->plugin->endSkill($this->player->getName(), $this->skill);
                            //$this->player->sendPopup("{$this->plugin->pre} 스킬, [ {$this->skill} ] 의 효과가 소멸되었습니다.");
                            $this->plugin->sendPopupDelay($this->player, "스킬, [ {$this->skill} ] 의 효과가 소멸되었습니다.");
                        }
                    }
                }, $SkillTime * 20);
        //$player->sendPopup("{$this->plugin->pre} 스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        $this->plugin->sendPopupDelay($player, "스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        $this->plugin->tutorial->check($player, 4);
        return true;
    }

    public function Passive_2(Player $player) {
        $SkillName = "재생의 오라";
        if (!$this->plugin->isSkill($player->getName(), $SkillName))
            return false;
        if ($this->plugin->getSkillLevel($player->getName(), $SkillName) <= 0)
            return false;
        if ($this->plugin->isUsingSkill($player->getName(), $SkillName)) {
            //$player->sendPopup("{$this->plugin->pre} 이미 시전중입니다.");
            $this->plugin->sendPopupDelay($player, "이미 시전중입니다.");
            return false;
        }
        $SkillLevel = $this->plugin->getSkillLevel($player->getName(), $SkillName);
        $SkillMana = $this->plugin->getSkillMana($SkillName, $SkillLevel);
        if ($this->plugin->util->getMp($player->getName()) < $this->plugin->getSkillMana($SkillName, $SkillLevel)) {
            //$player->sendPopup("{$this->plugin->pre} 마나가 부족합니다.");
            $this->plugin->sendPopupDelay($player, "마나가 부족합니다.");
            return false;
        }
        $SkillTime = $this->plugin->getSkillTime($SkillName, $SkillLevel);
        $this->plugin->util->reduceMp($player->getName(), $SkillMana);
        $this->plugin->SkillSound($player, 232);
        $this->plugin->useSkill($player->getName(), $SkillName);
        $this->plugin->getScheduler()->scheduleRepeatingTask(
                new class($this->plugin, $player, $SkillName, $this->plugin->list[$player->getName()][$SkillName]) extends Task {
                    public function __construct(SkillManager $plugin, Player $player, string $SkillName, int $count) {
                        $this->plugin = $plugin;
                        $this->player = $player;
                        $this->skill = $SkillName;
                        $this->count = $count;
                    }

                    public function onRun($currentTick) {
                        if ($this->player instanceof Player && $this->plugin->isUsingSkill($this->player->getName(), $this->skill) && $this->count == $this->plugin->list[$this->player->getName()][$this->skill]) {
                            $heal = explode(":", $this->plugin->sdata[$this->skill]["효과"][$this->plugin->getSkillLevel($this->player->getName(), $this->skill)]);
                            $this->player->heal(new \pocketmine\event\entity\EntityRegainHealthEvent($this->player, $heal[0], 3));
                            $this->plugin->util->addMp($this->player->getName(), $heal[1]);
                        } else {
                            $this->plugin->getScheduler()->cancelTask($this->getTaskId());
                        }
                    }
                }, 30 * 20);
        $this->plugin->getScheduler()->scheduleDelayedTask(
                new class($this->plugin, $player, $SkillName, $this->plugin->list[$player->getName()][$SkillName]) extends Task {
                    public function __construct(SkillManager $plugin, Player $player, string $SkillName, int $count) {
                        $this->plugin = $plugin;
                        $this->player = $player;
                        $this->skill = $SkillName;
                        $this->count = $count;
                    }

                    public function onRun($currentTick) {
                        if ($this->player instanceof Player && $this->plugin->isUsingSkill($this->player->getName(), $this->skill) && $this->count == $this->plugin->list[$this->player->getName()][$this->skill]) {
                            $this->plugin->endSkill($this->player->getName(), $this->skill);
                            //$this->player->sendPopup("{$this->plugin->pre} 스킬, [ {$this->skill} ] 의 효과가 소멸되었습니다.");
                            $this->plugin->sendPopupDelay($this->player, "스킬, [ {$this->skill} ] 의 효과가 소멸되었습니다.");
                        }
                    }
                }, $SkillTime * 20);
        //$player->sendPopup("{$this->plugin->pre} 스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        $this->plugin->sendPopupDelay($player, "스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        $this->plugin->tutorial->check($player, 4);
        return true;
    }

    public function Active_1_Particle($player, $target) {
        $level = $target->getLevel();
        $vec = new Vector3($target->x, $target->y + 0.62, $target->z);
        for ($x = -1; $x < 2; $x++) {
            for ($z = -1; $z < 2; $z++) {
                for ($y = -1; $y < 2; $y++) {
                    $level->addParticle(new HugeExplodeParticle($vec->add($x, $y, $z)));
                }
            }
        }
        $player->getLevel()->broadcastLevelSoundEvent($target, LevelSoundEventPacket::SOUND_EXPLODE);
    }

    public function Active_1(Player $player) {
        $SkillName = "강타";
        if (!$this->plugin->isSkill($player->getName(), $SkillName))
            return false;
        if ($this->plugin->getSkillLevel($player->getName(), $SkillName) <= 0)
            return false;
        if ($this->plugin->isUsingSkill($player->getName(), $SkillName)) {
            //$player->sendPopup("{$this->plugin->pre} 이미 시전중입니다.");
            $this->plugin->sendPopupDelay($player, "이미 시전중입니다.");
            return false;
        }
        $SkillLevel = $this->plugin->getSkillLevel($player->getName(), $SkillName);
        $SkillMana = $this->plugin->getSkillMana($SkillName, $SkillLevel);
        if ($this->plugin->util->getMp($player->getName()) < $this->plugin->getSkillMana($SkillName, $SkillLevel)) {
            //$player->sendPopup("{$this->plugin->pre} 마나가 부족합니다.");
            $this->plugin->sendPopupDelay($player, "마나가 부족합니다.");
            return false;
        }
        $this->plugin->util->reduceMp($player->getName(), $SkillMana);
        $this->plugin->SkillSound($player, 232);
        $this->plugin->useSkill($player->getName(), $SkillName);
        $this->plugin->getScheduler()->scheduleDelayedTask(
                new class($this->plugin, $player, $SkillName, $this->plugin->list[$player->getName()][$SkillName]) extends Task {
                    public function __construct(SkillManager $plugin, Player $player, string $SkillName, int $count) {
                        $this->plugin = $plugin;
                        $this->player = $player;
                        $this->skill = $SkillName;
                        $this->count = $count;
                    }

                    public function onRun($currentTick) {
                        if ($this->player instanceof Player && $this->plugin->isUsingSkill($this->player->getName(), $this->skill) && $this->count == $this->plugin->list[$this->player->getName()][$this->skill]) {
                            $this->plugin->endSkill($this->player->getName(), $this->skill);
                            //$this->player->sendPopup("{$this->plugin->pre} 스킬, [ {$this->skill} ] (이)가 미사용으로 시전이 취소되었습니다.");
                            $this->plugin->sendPopupDelay($this->player, "스킬, [ {$this->skill} ] 의 효과가 소멸되었습니다.");
                        }
                    }
                }, 60 * 20);
        //$player->sendPopup("{$this->plugin->pre} 스킬, [ {$SkillName} ] (을)를 시전할 준비가 되었습니다.");
        $this->plugin->sendPopupDelay($player, "스킬, [ {$SkillName} ] (을)를 시전할 준비가 되었습니다.");
        $this->plugin->tutorial->check($player, 4);
        return true;
    }

    public function Active_2_Particle($player, $target) {
        $level = $target->getLevel();
        $distance = $target->distance($player);
        $vec = new Vector3($target->x, $target->y + 0.62, $target->z);
        //$level->addParticle(new HugeExplodeParticle($vec));
        for ($x = -1; $x < 2; $x++) {
            for ($z = -1; $z < 2; $z++) {
                for ($y = -1; $y < 2; $y++) {
                    $level->addParticle(new CriticalParticle($vec->add($x, $y, $z)));
                }
            }
        }
    }

    public function Active_2(Player $player) {
        $SkillName = "일섬";
        if (!$this->plugin->isSkill($player->getName(), $SkillName))
            return false;
        if ($this->plugin->getSkillLevel($player->getName(), $SkillName) <= 0)
            return false;
        if ($this->plugin->isUsingSkill($player->getName(), $SkillName)) {
            //$player->sendPopup("{$this->plugin->pre} 이미 시전중입니다.");
            $this->plugin->sendPopupDelay($player, "이미 시전중입니다.");
            return false;
        }
        $SkillLevel = $this->plugin->getSkillLevel($player->getName(), $SkillName);
        $SkillMana = $this->plugin->getSkillMana($SkillName, $SkillLevel);
        if ($this->plugin->util->getMp($player->getName()) < $this->plugin->getSkillMana($SkillName, $SkillLevel)) {
            //$player->sendPopup("{$this->plugin->pre} 마나가 부족합니다.");
            $this->plugin->sendPopupDelay($player, "마나가 부족합니다.");
            return false;
        }
        $this->plugin->util->reduceMp($player->getName(), $SkillMana);
        $this->plugin->SkillSound($player, 232);
        $this->plugin->useSkill($player->getName(), $SkillName);
        $this->plugin->getScheduler()->scheduleDelayedTask(
                new class($this->plugin, $player, $SkillName, $this->plugin->list[$player->getName()][$SkillName]) extends Task {
                    public function __construct(SkillManager $plugin, Player $player, string $SkillName, int $count) {
                        $this->plugin = $plugin;
                        $this->player = $player;
                        $this->skill = $SkillName;
                        $this->count = $count;
                    }

                    public function onRun($currentTick) {
                        if ($this->player instanceof Player && $this->plugin->isUsingSkill($this->player->getName(), $this->skill) && $this->count == $this->plugin->list[$this->player->getName()][$this->skill]) {
                            $this->plugin->endSkill($this->player->getName(), $this->skill);
                            //$this->player->sendPopup("{$this->plugin->pre} 스킬, [ {$this->skill} ] (이)가 미사용으로 시전이 취소되었습니다.");
                            $this->plugin->sendPopupDelay($this->player, "스킬, [ {$this->skill} ] (이)가 미사용으로 시전이 취소되었습니다.");
                        }
                    }
                }, 60 * 20);
        //$player->sendPopup("{$this->plugin->pre} 스킬, [ {$SkillName} ] (을)를 시전할 준비가 되었습니다.");
        $this->plugin->sendPopupDelay($player, "스킬, [ {$SkillName} ] (을)를 시전할 준비가 되었습니다.");
        $this->plugin->tutorial->check($player, 4);
        return true;
    }

    public function Active_3(Player $player) {
        $SkillName = "가속";
        if (!$this->plugin->isSkill($player->getName(), $SkillName))
            return false;
        if ($this->plugin->getSkillLevel($player->getName(), $SkillName) <= 0)
            return false;
        $SkillLevel = $this->plugin->getSkillLevel($player->getName(), $SkillName);
        $SkillMana = $this->plugin->getSkillMana($SkillName, $SkillLevel);
        if ($this->plugin->util->getMp($player->getName()) < $this->plugin->getSkillMana($SkillName, $SkillLevel)) {
            //$player->sendPopup("{$this->plugin->pre} 마나가 부족합니다.");
            $this->plugin->sendPopupDelay($player, "마나가 부족합니다.");
            return false;
        }
        $this->plugin->util->reduceMp($player->getName(), $SkillMana);
        $this->plugin->SkillSound($player, 232);
        $this->Active_3_Motion($player);
        $this->plugin->ability->addBornPoint($player->getName(), "신속", 1);
        //$player->sendPopup("{$this->plugin->pre} 스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        $this->plugin->sendPopupDelay($player, "스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        $this->plugin->tutorial->check($player, 4);
        return true;
    }

    public function Active_3_Motion($player) {
        $x = -\sin($player->yaw / 180 * M_PI);
        $z = \cos($player->yaw / 180 * M_PI);
        $peek = $player->getDirectionVector();
        $vec = new Vector3(3 * $peek->x, 0, 3 * $peek->z);
        $player->setMotion($vec);
        $this->plugin->SkillSound($player, 237);
        $this->plugin->SkillSound($player, 182);
    }
}

<?php
namespace SkillManager;

use AbilityManager\AbilityManager;
use Core\Core;
use Core\util\Util;
use Equipments\Equipments;
use GuiLibrary\GuiLibrary;
use Monster\mob\MonsterBase;
use Monster\mob\PersonBase;
use PartyManager\PartyManager;
use pocketmine\command\{Command, CommandSender};
use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;
use TutorialManager\TutorialManager;
use UiLibrary\UiLibrary;

class SkillManager extends PluginBase {
    private static $instance = null;
    public $pre = "§e•";
    //public $pre = "§l§e[ §f스킬 §e]§r§e";
    public $count = 0;

    public static function getInstance() {
        return self::$instance;
    }

    public function onLoad() {
        self::$instance = $this;
    }

    public function onEnable() {
        /*$this->getServer()->getLogger()->notice($this->getServer()->getDataPath());
        @mkdir("/root/Real");
        $this->real = new Config("/root/Real/real.yml", Config::YAML);
        $this->rdata = $this->real->getAll();
        $this->rdata["awd"] = "오 씨발";
        $this->real->setAll($this->rdata);
        $this->real->save();*/

        @mkdir($this->getDataFolder());
        $this->saveResource("skills.yml");
        $this->skill = new Config($this->getDataFolder() . "skills.yml", Config::YAML);
        $this->user = new Config($this->getDataFolder() . "user.yml", Config::YAML);
        $this->sdata = $this->skill->getAll();
        $this->udata = $this->user->getAll();
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->util = new Util(Core::getInstance());
        $this->ui = UiLibrary::getInstance();
        $this->gui = GuiLibrary::getInstance();
        $this->equipments = Equipments::getInstance();
        $this->ability = AbilityManager::getInstance();
        $this->tutorial = TutorialManager::getInstance();
        $this->party = PartyManager::getInstance();

        $this->Adventurer = new Adventurer($this);
        $this->Knight = new Knight($this);
        $this->Archer = new Archer($this);
        $this->Wizard = new Wizard($this);
        $this->Priest = new Priest($this);
    }

    public function onDisable() {
        foreach ($this->udata as $key => $value) {
            $this->udata[$key]["시전중"] = [];
        }
        $this->save();
    }

    public function save() {
        $this->user->setAll($this->udata);
        $this->user->save();
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, $args): bool {
        if ($cmd->getName() == "스킬") {
            if ($sender->isOp()) {
                if (!isset($args[0]) || !isset($args[1]) || !is_numeric($args[1]))
                    return false;
                $this->setSkillPoint($args[0], $args[1]);
            }
            return true;
        }
        if ($cmd->getName() == "소리") {
            if ($sender->isOp()) {
                if (!isset($args[0]) || !is_numeric($args[0]))
                    return false;
                $sender->getLevel()->broadcastLevelSoundEvent($sender, $args[0]);
            }
            return true;
        }
        return false;
    }

    public function setSkillPoint(string $name, int $point) {
        $this->udata[$name]["스킬 포인트"] = $point;
    }

    public function sendPopupDelay(Player $player, string $text) {
        $this->getScheduler()->scheduleDelayedTask(
                new class($this, $player, $text) extends Task {
                    public function __construct(SkillManager $plugin, Player $player, string $text) {
                        $this->plugin = $plugin;
                        $this->player = $player;
                        $this->text = $text;
                    }

                    public function onRun($currentTick) {
                        if ($this->player instanceof Player) {
                            $this->player->sendPopup("§r§e• " . $this->text);
                        }
                    }
                }, 2);
    }

    public function addSkillPoint(string $name, int $point) {
        if (!isset($this->udata[$name]) || $point < 0)
            return false;
        $this->udata[$name]["스킬 포인트"] += $point;
        return true;
    }

    public function getSkillEffect(string $SkillName, int $level) {//패시브
        if (!isset($this->sdata[$SkillName]["이펙트"]))
            return false;
        else
            return explode(":", $this->sdata[$SkillName]["이펙트"][$level])[0];
    }

    public function getSkillEffectPower(string $SkillName, int $level) {//패시브
        if (!isset($this->sdata[$SkillName]["이펙트"]))
            return false;
        else
            return explode(":", $this->sdata[$SkillName]["이펙트"][$level])[1];
    }

    public function getSkillTime(string $SkillName, int $level) {//패시브
        if ($this->sdata[$SkillName]["타입"] !== "버프")
            return null;
        else
            return $this->sdata[$SkillName]["시전"][$level];
    }

    public function useSkill(string $name, string $SkillName) {
        if ($this->isUsingSkill($name, $SkillName))
            return false;
        else {
            $this->list[$name][$SkillName] = $this->count++;
            array_push($this->udata[$name]["시전중"], $SkillName);
            return true;
        }
    }

    public function isUsingSkill(string $name, string $SkillName) {
        return in_array($SkillName, $this->udata[$name]["시전중"]);
    }

    public function endAllSkill(string $name) {
        foreach ($this->udata[$name]["시전중"] as $key => $SkillName) {
            $this->endSkill($name, $SkillName);
        }
    }

    public function endSkill(string $name, string $SkillName) {
        if (!$this->isUsingSkill($name, $SkillName))
            return false;
        else {
            if (($player = $this->getServer()->getPlayer($name)) instanceof Player && isset($this->sdata[$SkillName]["이펙트"])) {
                foreach ($this->sdata[$SkillName]["이펙트"] as $key => $Id) {
                    $Id = explode(":", $Id)[0];
                    $player->removeEffect($Id);
                }
            }
            unset($this->udata[$name]["시전중"][array_search($SkillName, $this->udata[$name]["시전중"])]);
            return true;
        }
    }

    public function SkillUI(Player $player) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            if (!isset($data[0])) return;
            if ($data[0] == 0) {
                $this->SkillWindow($player);
            }
            if ($data[0] == 1) {
                $this->SkillTreeUI($player);
            }
            if ($data[0] == 2) {
                $form = $this->ui->SimpleForm(function (Player $player, array $data) {
                });
                $form->setTitle("Tele Skill");
                foreach ($this->udata[$player->getName()]["스킬"] as $SkillName => $SkillLevel) {
                    $form->addButton("§l{$SkillName} §r§8| Lv. §8§l{$SkillLevel}");
                }
                $form->sendToPlayer($player);
            }
            if ($data[0] == 3) {
                $this->SkillInfoUI($player);
            }
        });
        $form->setTitle("Tele Skill");
        $form->addButton("§l스킬 퀵슬롯 설정\n§r§8스킬 퀵슬롯을 설정합니다.");
        $form->addButton("§l스킬 트리\n§r§8스킬을 관리합니다.");
        $form->addButton("§l보유 스킬 확인\n§r§8보유 스킬을 확인합니다.");
        $form->addButton("§l스킬 가이드북\n§r§8습득 가능한 스킬들의 정보를 확인합니다.");
        $form->sendToPlayer($player);
    }

    public function SkillWindow(Player $player) {
        $tile = $this->gui->addWindow($player, "스탯 퀵슬롯 설정", 1);
        $this->skillInv[$player->getName()] = true;
        $slot = 0;
        foreach ($this->udata[$player->getName()]["스킬"] as $SkillName => $SkillLevel) {
            if ($SkillLevel <= 0 || $this->getSkillType($SkillName) == "패시브" || 36 <= $slot)
                continue;
            else {
                if ($this->getLore($SkillName) !== null)
                    $item = $this->SkillItem($SkillName . "\n§r§fLv. " . $SkillLevel . "\n§r§f{$this->getLore($SkillName)}");
                else
                    $item = $this->SkillItem($SkillName . "\n§r§fLv. " . $SkillLevel);
            }
            for ($i = 1; $i <= 4; $i++) {
                if ($player->getInventory()->getItem($i)->getCustomName() == $item->getCustomName()) {
                    $tile[0]->getInventory()->setItem($i + 45, $item);
                    $isset = true;
                    break;
                }
            }
            if (isset($isset)) {
                unset($isset);
                $slot++;
                continue;
            } else {
                $tile[0]->getInventory()->setItem($slot, $item);
                $slot++;
            }
        }
        for ($i = 36; $i < 54; $i++) {
            if (36 <= $i && $i < 45) {
                $item = new Item(383, 38);
                $item->setCustomName("§l§r잠긴칸");
                $tile[0]->getInventory()->setItem($i, $item);
            } else {
                $item = $player->getInventory()->getItem($i - 45);
                if (46 <= $i && $i <= 49)
                    continue;
                //$item = new Item(0, 0);
                $tile[0]->getInventory()->setItem($i, $item);
            }
        }
        $tile[0]->send($player);
    }

    public function getSkillType(string $SkillName) {
        if (!isset($this->sdata[$SkillName]["타입"]))
            return null;
        return $this->sdata[$SkillName]["타입"];
    }

    public function getLore(string $SkillName) {
        if (!isset($this->sdata[$SkillName]["설명"]))
            return null;
        return $this->sdata[$SkillName]["설명"];
    }

    private function SkillItem(string $SkillName) {
        //$item = new Item(387, 0);
        $SkillName_ = explode("\n", $SkillName)[0];
        if ($this->getSkillType($SkillName_) == null)
            $item = new Item(387, 0);
        elseif ($this->getSkillType($SkillName_) == "버프")
            $item = new Item(351, 11);
        elseif ($this->getSkillType($SkillName_) == "액티브")
            $item = new Item(351, 0);
        else
            $item = new Item(387, 0);
        $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(22), 10));
        $item->setCustomName("§r§l§b" . $SkillName);
        return $item;
    }

    private function SkillTreeUI(Player $player) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            if (!isset($data[0])) return false;
            $SkillName = $this->skillTree[$player->getName()][$data[0]];
            unset($this->skillTree[$player->getName()]);
            if (!$this->isSkill($player->getName(), $SkillName)) {
                $player->sendMessage("{$this->pre} 해당 스킬은 아직 사용할 수 없습니다.");
                return false;
            }
            if ($this->getSkillLevel($player->getName(), $SkillName) >= $this->getSkillMaxLevel($SkillName)) {
                $player->sendMessage("{$this->pre} 해당 스킬은 이미 최대치 입니다.");
                return false;
            }
            if ($this->getSkillPoint($player->getName()) <= 0) {
                $player->sendMessage("{$this->pre} 스킬 포인트가 부족합니다.");
                return false;
            }
            $this->check($player, $SkillName, 1);
        });
        $form->setTitle("Tele Skill");
        $form->setContent("§9§l▶ §r§f보유한 스킬 포인트: §a{$this->getSkillPoint($player->getName())}");
        $count = 0;
        foreach ($this->sdata as $SkillName => $info) {
            if ($this->getSkillJob($SkillName) !== null && !in_array($this->util->getJob($player->getName()), $this->getSkillJob($SkillName)))
                continue;
            $this->skillTree[$player->getName()][$count] = $SkillName;
            if ($this->getPrecedenceSkill($SkillName) == null)
                $content = "없음";
            else {
                if (!$this->isSkill($player->getName(), $this->getPrecedenceSkill($SkillName)))
                    $content = "§c{$this->getPrecedenceSkill($SkillName)} §8| Lv. §c0";
                elseif ($this->getSkillLevel($player->getName(), $this->getPrecedenceSkill($SkillName)) < $this->getPrecedenceSkillLevel($SkillName))
                    $content = "§8{$this->getPrecedenceSkill($SkillName)} §8| Lv. §c{$this->getPrecedenceSkillLevel($SkillName)}";
                else
                    $content = "§8{$this->getPrecedenceSkill($SkillName)} §8| Lv. §8{$this->getPrecedenceSkillLevel($SkillName)}";
            }

            if (!$this->isSkill($player->getName(), $SkillName)) {
                $SkillName = "§l§c{$SkillName}";
                $SkillLevel = 0;
            } else {
                $SkillLevel = $this->getSkillLevel($player->getName(), $SkillName);
                $SkillName = "§l§8{$SkillName}";
            }

            $form->addButton("{$SkillName} §r§8| Lv. {$SkillLevel}\n§r§8선행스킬: {$content}");
            $count++;
        }
        $form->sendToPlayer($player);
    }

    private function check(Player $player, string $SkillName, int $amount) {
        $this->skillTree[$player->getName()] = "{$SkillName}:{$amount}";
        $form = $this->ui->ModalForm(function (Player $player, array $data) {
            $info = explode(":", $this->skillTree[$player->getName()]);
            unset($this->skillTree[$player->getName()]);
            if ($data[0] == true) {
                $this->LevelUpSkill($player->getName(), $info[0], $info[1]);
                $this->SkillTreeUI($player);
            } else {
                $this->SkillTreeUI($player);
                return false;
            }
        });
        $form->setTitle("Tele Skill");
        $form->setContent("\n§f정말 스킬, [ {$SkillName} ] 의 레벨을 Lv. {$amount} 상승 시키겠습니까?");
        $form->setButton1("§l§8[예]");
        $form->setButton2("§l§8[아니오]");
        $form->sendToPlayer($player);
    }

    public function LevelUpSkill(string $name, string $SkillName, int $amount) {
        if (!$this->isSkill($name, $SkillName) || $this->getSkillPoint($name) < $amount)
            return false;
        else {
            if ($this->getSkillLevel($name, $SkillName) >= $this->getSkillMaxLevel($SkillName))
                return false;
            else {
                // TODO: 스킬 레벨업 당 스킬 피지컬 추가
                switch ($SkillName) {

                    case "가속":
                        $this->udata[$name]["스킬"]["가속"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 가속 ] (이)가 Lv. {$this->getSkillLevel($name, "가속")}(이)가 되었습니다.");
                        }
                        break;

                    case "산뜻한 발걸음":
                        $this->udata[$name]["스킬"]["산뜻한 발걸음"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 산뜻한 발걸음 ] (이)가 Lv. {$this->getSkillLevel($name, "산뜻한 발걸음")}(이)가 되었습니다.");
                        }
                        break;

                    case "재생의 오라":
                        $this->udata[$name]["스킬"]["재생의 오라"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 재생의 오라 ] (이)가 Lv. {$this->getSkillLevel($name, "재생의 오라")}(이)가 되었습니다.");
                        }
                        break;

                    case "강타":
                        $this->udata[$name]["스킬"]["강타"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 강타 ] (이)가 Lv. {$this->getSkillLevel($name, "강타")}(이)가 되었습니다.");
                        }
                        if ($this->getSkillLevel($name, "강타") >= $this->getPrecedenceSkillLevel("일섬") && !$this->isSkill($name, "일섬")) {
                            if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                                $player->sendMessage("{$this->pre} 스킬, [ 일섬 ] (이)가 오픈되었습니다.");
                            }
                            $this->addSkill($name, "일섬");
                        }
                        break;

                    case "일섬":
                        $this->udata[$name]["스킬"]["일섬"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 일섬 ] (이)가 Lv. {$this->getSkillLevel($name, "일섬")}(이)가 되었습니다.");
                        }
                        break;

                    case "순섬":
                        $this->udata[$name]["스킬"]["순섬"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 순섬 ] (이)가 Lv. {$this->getSkillLevel($name, "순섬")}(이)가 되었습니다.");
                        }
                        break;

                    case "파워 블러스트":
                        $this->udata[$name]["스킬"]["파워 블러스트"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 파워 블러스트 ] (이)가 Lv. {$this->getSkillLevel($name, "파워 블러스트")}(이)가 되었습니다.");
                        }
                        break;

                    case "나이트의 집중":
                        $arr = [10, 40, 70];
                        if ($this->getSkillLevel($name, "나이트의 집중") <= 0 || $this->udata[$name]["etc"]["나이트의 집중"] <= 0)
                            $old_level = 0;
                        else
                            $old_level = $this->udata[$name]["etc"]["나이트의 집중"];
                        if ($old_level !== 0) {
                            $effect = explode(":", $this->getSkillInfo("나이트의 집중", $this->getSkillLevel($name, "나이트의 집중")));
                            $old_effect = $effect[array_search($old_level, $arr)];
                            $this->util->addATK($name, $old_effect * -1, "Skill");
                        }//여기까지 기존량 제거
                        $this->udata[$name]["스킬"]["나이트의 집중"] += $amount;
                        if (10 <= $this->util->getLevel($name) && $this->util->getLevel($name) < 40)
                            $this->udata[$name]["etc"]["나이트의 집중"] = 10;
                        if (40 <= $this->util->getLevel($name) && $this->util->getLevel($name) < 70)
                            $this->udata[$name]["etc"]["나이트의 집중"] = 40;
                        if (70 <= $this->util->getLevel($name))
                            $this->udata[$name]["etc"]["나이트의 집중"] = 70;
                        $this->reduceSkillPoint($name, $amount);
                        $new_level = $this->udata[$name]["etc"]["나이트의 집중"];
                        $effect = explode(":", $this->getSkillInfo("나이트의 집중", $this->getSkillLevel($name, "나이트의 집중")));
                        $new_effect = $effect[array_search($new_level, $arr)];
                        $this->util->addATK($name, $new_effect, "Skill");
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 나이트의 집중 ] (이)가 Lv. {$this->getSkillLevel($name, "나이트의 집중")}(이)가 되었습니다.");
                        }
                        break;

                    case "나이트의 정신":
                        $arr = [10, 40, 70];
                        if ($this->getSkillLevel($name, "나이트의 정신") <= 0 || $this->udata[$name]["etc"]["나이트의 정신"] <= 0)
                            $old_level = 0;
                        else
                            $old_level = $this->udata[$name]["etc"]["나이트의 정신"];
                        if ($old_level !== 0) {
                            $effect = explode(":", $this->getSkillInfo("나이트의 정신", $this->getSkillLevel($name, "나이트의 정신")));
                            $old_effect = $effect[array_search($old_level, $arr)];
                            $this->util->addDEF($name, $old_effect * -1, "Skill");
                            $this->util->addMDEF($name, $old_effect * -1, "Skill");
                        }//여기까지 기존량 제거
                        $this->udata[$name]["스킬"]["나이트의 정신"] += $amount;
                        if (10 <= $this->util->getLevel($name) && $this->util->getLevel($name) < 40)
                            $this->udata[$name]["etc"]["나이트의 정신"] = 10;
                        if (40 <= $this->util->getLevel($name) && $this->util->getLevel($name) < 70)
                            $this->udata[$name]["etc"]["나이트의 정신"] = 40;
                        if (70 <= $this->util->getLevel($name))
                            $this->udata[$name]["etc"]["나이트의 정신"] = 70;
                        $this->reduceSkillPoint($name, $amount);
                        $new_level = $this->udata[$name]["etc"]["나이트의 정신"];
                        $effect = explode(":", $this->getSkillInfo("나이트의 집중", $this->getSkillLevel($name, "나이트의 집중")));
                        $new_effect = $effect[array_search($new_level, $arr)];
                        $this->util->addDEF($name, $new_effect, "Skill");
                        $this->util->addMDEF($name, $new_effect, "Skill");
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 나이트의 정신 ] (이)가 Lv. {$this->getSkillLevel($name, "나이트의 정신")}(이)가 되었습니다.");
                        }
                        break;

                    case "연속 사격":
                        $this->udata[$name]["스킬"]["연속 사격"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 연속 사격 ] (이)가 Lv. {$this->getSkillLevel($name, "연속 사격")}(이)가 되었습니다.");
                        }
                        break;

                    case "스나이핑":
                        $this->udata[$name]["스킬"]["스나이핑"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 스나이핑 ] (이)가 Lv. {$this->getSkillLevel($name, "스나이핑")}(이)가 되었습니다.");
                        }
                        break;

                    case "아처의 정신":
                        if ($this->getSkillLevel($name, "아처의 정신") > 0) {
                            $old_speed = $this->getSkillInfo("아처의 정신", $this->getSkillLevel($name, "아처의 정신"));
                            $this->udata[$name]["스킬"]["아처의 정신"] += $amount;
                            $new_speed = $this->getSkillInfo("아처의 정신", $this->getSkillLevel($name, "아처의 정신"));
                            $this->reduceSkillPoint($name, $amount);
                            if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                                $player->sendMessage("{$this->pre} 스킬, [ 아처의 정신 ] (이)가 Lv. {$this->getSkillLevel($name, "아처의 정신")}(이)가 되었습니다.");
                                $attribute = $player->getAttributeMap()->getAttribute(5)->getValue();
                                $player->getAttributeMap()->getAttribute(Attribute::MOVEMENT_SPEED)->setValue($attribute * (1 / $old_speed) * $new_speed);
                            }
                        } else {
                            $this->udata[$name]["스킬"]["아처의 정신"] += $amount;
                            $new_speed = $this->getSkillInfo("아처의 정신", $this->getSkillLevel($name, "아처의 정신"));
                            $this->reduceSkillPoint($name, $amount);
                            if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                                $player->sendMessage("{$this->pre} 스킬, [ 아처의 정신 ] (이)가 Lv. {$this->getSkillLevel($name, "아처의 정신")}(이)가 되었습니다.");
                                $attribute = $player->getAttributeMap()->getAttribute(5)->getValue();
                                $player->getAttributeMap()->getAttribute(Attribute::MOVEMENT_SPEED)->setValue($attribute * $new_speed);
                            }
                        }

                        break;

                    case "아처의 집중":
                        $this->udata[$name]["스킬"]["아처의 집중"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 아처의 집중 ] (이)가 Lv. {$this->getSkillLevel($name, "아처의 집중")}(이)가 되었습니다.");
                        }
                        break;

                    case "마력탄":
                        $this->udata[$name]["스킬"]["마력탄"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 마력탄 ] (이)가 Lv. {$this->getSkillLevel($name, "마력탄")}(이)가 되었습니다.");
                        }
                        break;

                    case "라이트닝 플레어":
                        $this->udata[$name]["스킬"]["라이트닝 플레어"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 라이트닝 플레어 ] (이)가 Lv. {$this->getSkillLevel($name, "라이트닝 플레어")}(이)가 되었습니다.");
                        }
                        break;

                    case "위자드의 집중":
                        $this->udata[$name]["스킬"]["위자드의 집중"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 위자드의 집중 ] (이)가 Lv. {$this->getSkillLevel($name, "위자드의 집중")}(이)가 되었습니다.");
                        }
                        break;

                    case "위자드의 정신":
                        $this->util->addHitHealMp($name, -1 * $this->getSkillInfo("위자드의 정신", $this->getSkillLevel($name, "위자드의 정신")));
                        $this->udata[$name]["스킬"]["위자드의 정신"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        $this->util->addHitHealMp($name, $this->getSkillInfo("위자드의 정신", $this->getSkillLevel($name, "위자드의 정신")));
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 위자드의 정신 ] (이)가 Lv. {$this->getSkillLevel($name, "위자드의 정신")}(이)가 되었습니다.");
                        }
                        break;

                    case "퓨어힐":
                        $this->udata[$name]["스킬"]["퓨어힐"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 퓨어힐 ] (이)가 Lv. {$this->getSkillLevel($name, "퓨어힐")}(이)가 되었습니다.");
                        }
                        break;

                    case "천벌":
                        $this->udata[$name]["스킬"]["천벌"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 천벌 ] (이)가 Lv. {$this->getSkillLevel($name, "천벌")}(이)가 되었습니다.");
                        }
                        break;

                    case "프리스트의 오라":
                        $this->udata[$name]["스킬"]["프리스트의 오라"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 프리스트의 오라 ] (이)가 Lv. {$this->getSkillLevel($name, "프리스트의 오라")}(이)가 되었습니다.");
                        }
                        break;

                    case "프리스트의 집중":
                        $this->udata[$name]["스킬"]["프리스트의 집중"] += $amount;
                        $this->reduceSkillPoint($name, $amount);
                        if (($player = $this->getServer()->getPlayer($name)) instanceof Player) {
                            $player->sendMessage("{$this->pre} 스킬, [ 프리스트의 집중 ] (이)가 Lv. {$this->getSkillLevel($name, "프리스트의 집중")}(이)가 되었습니다.");
                        }
                        break;

                    default:
                        return false;
                        break;
                }
                return true;
            }
        }
    }

    public function isSkill(string $name, string $SkillName) {
        return isset($this->udata[$name]["스킬"][$SkillName]);
    }

    public function getSkillPoint(string $name) {
        if (!isset($this->udata[$name]))
            return null;
        return $this->udata[$name]["스킬 포인트"];
    }

    public function getSkillLevel(string $name, string $SkillName) {
        if (!isset($this->udata[$name]["스킬"][$SkillName]))
            return null;
        else
            return $this->udata[$name]["스킬"][$SkillName];
    }

    public function getSkillMaxLevel(string $SkillName) {
        if (!isset($this->sdata[$SkillName]))
            return null;
        else
            return $this->sdata[$SkillName]["만렙"];
    }

    public function reduceSkillPoint(string $name, int $point) {
        if (!isset($this->udata[$name]) || $point < 0 || $this->getSkillPoint($name) < $point)
            return false;
        $this->udata[$name]["스킬 포인트"] -= $point;
        return true;
    }

    public function getPrecedenceSkillLevel(string $SkillName) {
        if (!isset($this->sdata[$SkillName]["선행"]))
            return null;
        return explode(":", $this->sdata[$SkillName]["선행"])[1];
    }

    public function addSkill(string $name, string $SkillName) {
        if (!$this->isSkill($name, $SkillName)) {
            $this->udata[$name]["스킬"][$SkillName] = 0;
            if ($SkillName == "나이트의 집중" || $SkillName == "나이트의 정신")
                $this->udata[$name]["etc"][$SkillName] = 0;
            return true;
        } else
            return false;
    }

    public function getSkillInfo(string $SkillName, int $level) {//패시브 스킬 전용
        if (!isset($this->sdata[$SkillName]["효과"]))
            return null;
        return $this->sdata[$SkillName]["효과"][$level];
    }

    public function getSkillJob(string $SkillName) {
        if (!isset($this->sdata[$SkillName]["직업"]))
            return null;
        return $this->sdata[$SkillName]["직업"];
    }

    public function getPrecedenceSkill(string $SkillName) {
        if (!isset($this->sdata[$SkillName]["선행"]))
            return null;
        return explode(":", $this->sdata[$SkillName]["선행"])[0];
    }

    public function SkillInfoUI(Player $player) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            if (!isset($data[0]) || !isset($this->list_[$player->getName()]) || !isset($this->list_[$player->getName()][$data[0]]))
                return false;
            $SkillName = $this->list_[$player->getName()][$data[0]];
            unset($this->list_[$player->getName()]);
            if ($this->isSkill($player->getName(), $SkillName))
                $isset = "§a보유중";
            else
                $isset = "§c미보유";
            $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            });
            $form->setTitle("Tele Skill");
            $text = "\n";
            $text .= "§f스킬 이름 : §f{$SkillName}\n";
            $text .= "\n";
            $text .= "§f스킬 타입 : §f{$this->getSkillType($SkillName)}\n";
            $text .= "\n";
            $text .= "§f보유여부 : {$isset}\n";
            $text .= "\n";
            $text .= "§f스킬 설명 :\n  - {$this->getLore($SkillName)}\n";
            $text .= "\n";
            if ($this->getSkillMana($SkillName, 1) !== null) {
                if ($isset == "§a보유중" && $this->getSkillLevel($player->getName(), $SkillName) > 0) {
                    if ($this->isPowerSkill($SkillName))
                        $text .= "§f소비 마나 : 현재 스킬레벨 기준, 마나 최대량의 {$this->getSkillMana($SkillName, $this->getSkillLevel($player->getName(), $SkillName))}％\n";
                    else
                        $text .= "§f소비 마나 : 현재 스킬레벨 기준, {$this->getSkillMana($SkillName, $this->getSkillLevel($player->getName(), $SkillName))} MP\n";
                    $text .= "\n";
                } else {
                    if ($this->isPowerSkill($SkillName))
                        $text .= "§f소비 마나 : 스킬 Lv. 1 기준, 마나 최대량의 {$this->getSkillMana($SkillName, 1)}％\n";
                    else
                        $text .= "§f소비 마나 : 스킬 Lv. 1 기준, {$this->getSkillMana($SkillName, 1)} MP\n";
                    $text .= "\n";
                }
            }
            if ($this->getNeedLevel($SkillName) == null)
                $needLevel = 0;
            else
                $needLevel = $this->getNeedLevel($SkillName);
            $text .= "§f필요 레벨 : §fLv. {$needLevel}\n";
            $text .= "\n";
            if ($this->getPrecedenceSkill($SkillName) !== null) {
                $text .= "§f선행 스킬 : {$this->getPrecedenceSkill($SkillName)} Lv. {$this->getPrecedenceSkillLevel($SkillName)}\n";
                $text .= "\n";
            }
            if ($this->getNeedAbility($SkillName) !== null) {
                $text .= "§f필요 재능 :\n";
                foreach ($this->getNeedAbility($SkillName) as $key => $ability) {
                    $text .= "  §f- {$ability}\n";
                }
                $text .= "\n";
            }
            if ($this->getPlusInbornAbilities($SkillName) !== null) {
                $text .= "§f시전시 상승 재능치 :\n";
                foreach ($this->getPlusInbornAbilities($SkillName) as $key => $ability) {
                    $text .= "  §f- {$ability} +1\n";
                }
            }
            $form->setContent($text);
            $form->sendToPlayer($player);
        });
        $form->setTitle("Tele Skill");
        $count = 0;
        foreach ($this->sdata as $SkillName => $value) {
            if ($this->getSkillJob($SkillName) !== null && !in_array($this->util->getJob($player->getName()), $this->getSkillJob($SkillName)))
                continue;
            $this->list_[$player->getName()][$count] = $SkillName;
            $form->addButton("§l{$SkillName}");
            $count++;
        }
        $form->sendToPlayer($player);
    }

    public function getSkillMana(string $SkillName, int $level) {
        if (!isset($this->sdata[$SkillName]["마나"][$level]))
            return null;
        else
            return $this->sdata[$SkillName]["마나"][$level];
    }

    public function isPowerSkill(string $SkillName) {
        if (!isset($this->sdata[$SkillName]) || !isset($this->sdata[$SkillName]["특수"]))
            return false;
        return $this->sdata[$SkillName]["특수"] == "궁극기";
    }

    public function getNeedLevel(string $SkillName) {
        if (!isset($this->sdata[$SkillName]["레벨"]))
            return null;
        return $this->sdata[$SkillName]["레벨"];
    }

    public function getNeedAbility(string $SkillName) {
        if (!isset($this->sdata[$SkillName]["재능"]))
            return null;
        return $this->sdata[$SkillName]["재능"];
    }

    public function getPlusInbornAbilities(string $SkillName) {
        if (!isset($this->sdata[$SkillName]) || !isset($this->sdata[$SkillName]["습득재능"]))
            return null;
        return $this->sdata[$SkillName]["습득재능"];
    }

    public function onDamage(Player $player, $target, EntityDamageByEntityEvent $ev, float $damage) {
        $old_damage = $damage;
        if ($this->isUsingSkill($player->getName(), "강타")) {
            $this->Adventurer->Active_1_Particle($player, $target);
            $player->sendMessage("{$this->pre} 스킬, [ 강타 ] (을)를 시전하였습니다.");
            $this->SkillSound($player, 237);
            $this->SkillSound($player, 48);
            $damage *= $this->getSkillDamage("강타", $this->getSkillLevel($player->getName(), "강타"));
            $this->endSkill($player->getName(), "강타");
        }
        if ($this->isUsingSkill($player->getName(), "일섬")) {
            $this->Adventurer->Active_2_Particle($player, $target);
            $player->sendMessage("{$this->pre} 스킬, [ 일섬 ] (을)를 시전하였습니다.");
            $this->SkillSound($player, 237);
            $this->SkillSound($player, 182);
            $per = $this->getLowRand("일섬", $this->getSkillLevel($player->getName(), "일섬"));
            $damage *= $this->getSkillDamage("일섬", $this->getSkillLevel($player->getName(), "일섬"));
            if ((mt_rand(1, 100) / 100) <= $per / 100 && stripos($target->getName(), "BOSS") === false) {
                $player->sendMessage("{$this->pre} 타겟의 기가 죽었습니다.");
                $target->setLow($this->getLowTime("일섬", $this->getSkillLevel($player->getName(), "일섬")));
            }
            $this->endSkill($player->getName(), "일섬");
        }
        if ($this->isUsingSkill($player->getName(), "순섬")) {
            $this->Knight->Active_1_Particle($player, $target);
            $damage *= $this->getSkillDamage("순섬", $this->getSkillLevel($player->getName(), "순섬"));
            $this->endSkill($player->getName(), "순섬");
        }
        if ($this->isUsingSkill($player->getName(), "파워 블러스트")) {
            $this->Knight->Active_2_Particle($player, $target);
            $player->sendMessage("{$this->pre} 스킬, [ 파워 블러스트 ] (을)를 시전하였습니다.");
            $this->SkillSound($player, 237);
            $this->SkillSound($player, 48);
            $damage *= $this->getSkillDamage("파워 블러스트", $this->getSkillLevel($player->getName(), "파워 블러스트"));
            $this->endSkill($player->getName(), "파워 블러스트");
        }
        if (isset($this->Archer_Active_1[$player->getId()])) {// 연속 사격
            foreach ($this->Archer_Active_1[$player->getId()] as $key => $value) {
                if (!$value->isClosed()) {
                    $dam = explode(":", $this->getSkillDamage("연속 사격", $this->getSkillLevel($player->getName(), "연속 사격")));
                    if (2 < $key)
                        $dam[$key] = 1;
                    $damage *= ($dam[$key] + 1);
                    unset($this->Archer_Active_1[$player->getId()][$key]);
                    if ($key == 3)
                        unset($this->Archer_Active_1[$player->getId()]);
                    break;
                } else {
                    unset($this->Archer_Active_1[$player->getId()][$key]);
                }
            }
        }
        if (isset($this->Archer_Active_2[$player->getId()])) {// 스나이핑
            $arrow = $this->Archer_Active_2[$player->getId()];
            if (!$arrow->isClosed()) {
                unset($this->Archer_Active_2[$player->getId()]);
                $damage *= $this->getSkillDamage("스나이핑", $this->getSkillLevel($player->getName(), "스나이핑"));
                $this->Archer_Active_2_second_target[$player->getId()] = [];
                foreach ($player->level->getNearbyEntities(new AxisAlignedBB($target->x - 3, $target->y - 3, $target->z - 3, $target->x + 3, $target->y + 3, $target->z + 3)) as $second_target) {
                    if ($second_target instanceof MonsterBase || $second_target instanceof PersonBase) {
                        if (count($this->Archer_Active_2_second_target[$player->getId()]) > 5)
                            break;
                        $this->Archer_Active_2_second_target[$player->getId()][$second_target->getId()][] = $second_target;
                        $source = new EntityDamageByEntityEvent($player, $second_target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 0);
                        $second_target->attack($source);
                    }
                }
                unset($this->Archer_Active_2[$player->getId()]);
                //$target->setTarget($player);
                /*$motion = $arrow->getMotion();
                $arrow = $this->Archer->Active_2_Shoot($target);
                if(!$arrow == null){
                  $arrow->setMotion($motion);
                  $this->Archer_Active_2[$player->getId()] = $arrow;
                }*/
            } else {
                unset($this->Archer_Active_2[$player->getId()]);
            }
        }
        if (isset($this->Archer_Active_2_second_target[$player->getId()]) && isset($this->Archer_Active_2_second_target[$player->getId()][$target->getId()])) { // 스나이핑 광역피해
            $damage *= ($this->getSkillDamage("스나이핑", $this->getSkillLevel($player->getName(), "스나이핑"))) - 1;
            $this->Archer->Active_2_Particle_2($target);
            unset($this->Archer_Active_2_second_target[$player->getId()][$target->getId()]);
        }
        if ($this->isSkill($player->getName(), "아처의 집중") && $this->getSkillLevel($player->getName(), "아처의 집중") > 0) {
            $per = $this->getSkillInfo("아처의 집중", $this->getSkillLevel($player->getName(), "아처의 집중"));
            if (mt_rand(1, 100) <= $per) {
                $damage += $old_damage;
                $player->sendPopup("§e§l아처의 집중!");
            }
        }
        if (isset($this->Wizard_Active_1[$player->getId()])) {
            $add = 0;
            if ($this->isSkill($player->getName(), "위자드의 집중") && $this->getSkillLevel($player->getName(), "위자드의 집중") > 0)
                $add = $this->getSkillInfo("위자드의 집중", $this->getSkillLevel($player->getName(), "위자드의 집중"));
            $damage *= ($this->getSkillDamage("마력탄", $this->getSkillLevel($player->getName(), "마력탄")) + $add);
            $this->Wizard->Active_1_Particle_2($target);
            unset($this->Wizard_Active_1[$player->getId()]);
        }
        if ($this->isUsingSkill($player->getName(), "라이트닝 플레어")) {
            $add = 0;
            if ($this->isSkill($player->getName(), "위자드의 집중") && $this->getSkillLevel($player->getName(), "위자드의 집중") > 0)
                $add = $this->getSkillInfo("위자드의 집중", $this->getSkillLevel($player->getName(), "위자드의 집중"));
            $this->Wizard->Active_2_Particle($player, $target);
            $player->sendMessage("{$this->pre} 스킬, [ 라이트닝 플레어 ] (을)를 시전하였습니다.");
            $damage *= ($this->getSkillDamage("라이트닝 플레어", $this->getSkillLevel($player->getName(), "라이트닝 플레어")) + $add);
            $this->endSkill($player->getName(), "라이트닝 플레어");
            $this->Wizard_Active_2[$player->getId()] = [];
            foreach ($player->level->getNearbyEntities(new AxisAlignedBB($target->x - 3, $target->y - 3, $target->z - 3, $target->x + 3, $target->y + 3, $target->z + 3)) as $second_target) {
                if ($second_target instanceof MonsterBase || $second_target instanceof PersonBase) {
                    $this->Wizard_Active_2[$player->getId()][$second_target->getId()] = $second_target;
                    $source = new EntityDamageByEntityEvent($player, $second_target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 0);
                    $second_target->attack($source);
                }
            }
        }
        if (isset($this->Wizard_Active_2[$player->getId()]) && isset($this->Wizard_Active_2[$player->getId()][$target->getId()])) { // 라이트닝 플레어 광역 피해
            $add = 0;
            if ($this->isSkill($player->getName(), "위자드의 집중") && $this->getSkillLevel($player->getName(), "위자드의 집중") > 0)
                $add = $this->getSkillInfo("위자드의 집중", $this->getSkillLevel($player->getName(), "위자드의 집중"));
            $this->Wizard->Active_2_Particle_2($player, $target);
            $damage *= ($this->getSkillDamage("라이트닝 플레어", $this->getSkillLevel($player->getName(), "라이트닝 플레어")) + $add);
            unset($this->Wizard_Active_2[$player->getId()][$target->getId()]);
        }
        if (isset($this->Priest_Active_2[$player->getId()]) && isset($this->Priest_Active_2[$player->getId()][$target->getId()])) { // 천벌 광역 피해
            $add = $this->getSkillDamage("천벌", $this->getSkillLevel($player->getName(), "천벌"));
            if ($this->isSkill($player->getName(), "프리스트의 집중") && $this->getSkillLevel($player->getName(), "프리스트의 집중") > 0)
                $add += ($this->getSkillInfo("프리스트의 집중", $this->getSkillLevel($player->getName(), "프리스트의 집중")));
            $damage *= $add;
            unset($this->Priest_Active_2[$player->getId()][$target->getId()]);
        }
        return $damage;
    }

    public function SkillSound(Player $player, int $id) {
        $pk = new LevelSoundEventPacket();
        $pk->sound = $id;
        $pk->position = $player;
        $pk->extraData = -1;
        $pk->isBabyMob = false;
        $pk->disableRelativeVolume = true;
        $player->dataPacket($pk);
    }

    public function getSkillDamage(string $SkillName, int $level) {//액티브
        if (!isset($this->sdata[$SkillName]))
            return null;
        else
            return $this->sdata[$SkillName]["데미지"][$level];
    }

    public function getLowRand(string $SkillName, int $level) {
        if (!isset($this->sdata[$SkillName]["기죽음"]))
            return null;
        else
            return (explode(":", $this->sdata[$SkillName]["기죽음"][$level]))[1];
    }

    public function getLowTime(string $SkillName, int $level) {
        if (!isset($this->sdata[$SkillName]["기죽음"]))
            return null;
        else
            return (explode(":", $this->sdata[$SkillName]["기죽음"][$level]))[0];
    }

    public function Skill(Player $player, string $SkillName) {
        $SkillName = str_replace(
                ["§0", "§1", "§2", "§3", "§4", "§5", "§6", "§7", "§8", "§9", "§a", "§b", "§c", "§d", "§e", "§f", "§l", "§o", "§r"],
                ["", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""],
                $SkillName);
        $SkillName = explode("\n", $SkillName)[0];

        if ($SkillName == "산뜻한 발걸음")
            $this->Adventurer->Passive_1($player);
        if ($SkillName == "재생의 오라")
            $this->Adventurer->Passive_2($player);
        if ($SkillName == "강타")
            $this->Adventurer->Active_1($player);
        if ($SkillName == "일섬")
            $this->Adventurer->Active_2($player);
        if ($SkillName == "가속")
            $this->Adventurer->Active_3($player);

        if ($SkillName == "순섬")
            $this->Knight->Active_1($player);
        if ($SkillName == "파워 블러스트")
            $this->Knight->Active_2($player);

        if ($SkillName == "연속 사격")
            $this->Archer->Active_1($player);
        if ($SkillName == "스나이핑")
            $this->Archer->Active_2($player);

        if ($SkillName == "마력탄")
            $this->Wizard->Active_1($player);
        if ($SkillName == "라이트닝 플레어")
            $this->Wizard->Active_2($player);

        if ($SkillName == "퓨어힐")
            $this->Priest->Active_1($player);
        if ($SkillName == "천벌")
            $this->Priest->Active_2($player);
    }

    public function adjust_skill(string $name) {
        if (10 <= $this->util->getLevel($name) && $this->util->getLevel($name) < 40)
            $new_level = 10;
        if (40 <= $this->util->getLevel($name) && $this->util->getLevel($name) < 70)
            $new_level = 40;
        if (70 <= $this->util->getLevel($name))
            $new_level = 70;
        if (count($this->udata[$name]["etc"]) <= 0)
            return false;
        foreach ($this->udata[$name]["etc"] as $SkillName => $value) {
            if ($this->udata[$name]["etc"][$SkillName] <= 0)
                continue;
            if ($this->udata[$name]["etc"][$SkillName] !== $new_level) {
                $old_level = $this->udata[$name]["etc"][$SkillName];
                $this->udata[$name]["etc"][$SkillName] = $new_level;
                if ($SkillName == "나이트의 집중") {
                    $arr = [10, 40, 70];
                    $effect = explode(":", $this->getSkillInfo("나이트의 집중", $this->getSkillLevel($name, "나이트의 집중")));
                    $old_effect = $effect[array_search($old_level, $arr)];
                    $new_effect = $effect[array_search($new_level, $arr)];
                    $this->util->addATK($name, $old_effect * -1, "Skill");
                    $this->util->addATK($name, $new_effect, "Skill");
                }
                if ($SkillName == "나이트의 정신") {
                    $arr = [10, 40, 70];
                    $effect = explode(":", $this->getSkillInfo("나이트의 정신", $this->getSkillLevel($name, "나이트의 정신")));
                    $old_effect = $effect[array_search($old_level, $arr)];
                    $new_effect = $effect[array_search($new_level, $arr)];
                    $this->util->addATK($name, $old_effect * -1, "Skill");
                    $this->util->addATK($name, $new_effect, "Skill");
                }
            }
        }
    }

    public function check_skill(string $name) {
        foreach ($this->sdata as $SkillName => $value) {
            if (!$this->isSkill($name, $SkillName)) {
                if ($this->getSkillJob($SkillName) !== null && !in_array($this->util->getJob($name), $this->getSkillJob($SkillName)))
                    continue;
                if ($this->getPrecedenceSkill($SkillName) !== null)
                    if (!$this->isSkill($name, $this->getPrecedenceSkill($SkillName)) || $this->getSkillLevel($name, $this->getPrecedenceSkill($SkillName)) < $this->getPrecedenceSkillLevel($SkillName))
                        continue;
                if ($this->getNeedLevel($SkillName) !== null)
                    if ($this->getNeedLevel($SkillName) > $this->util->getLevel($name))
                        continue;
                if ($this->getNeedAbility($SkillName) !== null) {
                    foreach ($this->getNeedAbility($SkillName) as $key => $ability) {
                        if (!in_array($ability, $this->ability->getBorns($name))) {
                            $isset = true;
                            break;
                        }
                    }
                    if (isset($isset)) {
                        unset($isset);
                        continue;
                    } else {
                        unset($isset);
                    }
                }
                $this->addSkill($name, $SkillName);
                if (($player = $this->getServer()->getPlayer($name)) instanceof Player)
                    $player->sendMessage("{$this->pre} 스킬, [ {$SkillName} ] (이)가 오픈되었습니다.");
            } else {
                if ($this->getSkillJob($SkillName) !== null && !in_array($this->util->getJob($name), $this->getSkillJob($SkillName))) {
                    $this->delSkill($name, $SkillName);
                    continue;
                }
                if ($this->getPrecedenceSkill($SkillName) !== null)
                    if (!$this->isSkill($name, $this->getPrecedenceSkill($SkillName)) || $this->getSkillLevel($name, $this->getPrecedenceSkill($SkillName)) < $this->getPrecedenceSkillLevel($SkillName)) {
                        $this->delSkill($name, $SkillName);
                        continue;
                    }
                if ($this->getNeedLevel($SkillName) !== null)
                    if ($this->getNeedLevel($SkillName) > $this->util->getLevel($name)) {
                        $this->delSkill($name, $SkillName);
                        continue;
                    }
                if ($this->getNeedAbility($SkillName) !== null) {
                    foreach ($this->getNeedAbility($SkillName) as $key => $ability) {
                        if (!in_array($ability, $this->ability->getBorns($name))) {
                            $isset = true;
                            break;
                        }
                    }
                    if (isset($isset)) {
                        unset($isset);
                        $this->delSkill($name, $SkillName);
                        continue;
                    } else {
                        unset($isset);
                    }
                }
            }
        }
    }

    public function delSkill(string $name, string $SkillName) {
        unset($this->udata[$name]["스킬"][$SkillName]);
        return true;
    }

}

<?php
/**
 * @name Prison
 * @author alvin0319
 * @main alvin0319\Prison
 * @version 1.0.0
 * @api 4.0.0
 */
namespace alvin0319;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\level\Position;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\utils\Internet;
use pocketmine\math\Vector3;

class Prison extends PluginBase implements Listener{
    private $config;
    private $db;
    
    public function onEnable() : void{
	$this->getLogger()->info("§b인식이 되었습니다.");
	$this->getServer()->getPluginManager()->registerEvents($this, $this);
        @mkdir($this->getDataFolder());
        $this->config = new Config($this->getDataFolder() . "Config.yml", Config::YAML);
        $this->db = $this->config->getAll();
        $this->cmd = new \pocketmine\command\PluginCommand("감옥", $this);
        $this->cmd->setDescription("감옥");
        $this->getServer()->getCommandMap()->register("감옥", $this->cmd);
        $this->getScheduler()->scheduleRepeatingTask(new PrisonTask($this), 20 * 60);
    }
    public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $name = strtolower($player->getName());
        if (! isset($this->db[$name])) {
            $this->db[$name] ["감옥"] = 0 . ":" . 0;//감옥번호 . ":" . 숫자
        }
    }
    public function onMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        $name = strtolower($player->getName());
        $x = (int) round($player->x - 0.5);
        $y = (int) round($player->y - 1);
        $z = (int) round($player->z - 0.5);
        $id = $player->getLevel()->getBlock(new Vector3($x, $y, $z))->getId();
        $data = $player->getLevel()->getBlock(new Vector3($x, $y, $z))->getDamage();
        if (isset($this->db[$name] ["감옥"])) {
            $a = explode(":", $this->db[$name] ["감옥"]);
            if ($a[1] > 0) {
                if (($id === $this->db["코드"] and $data === $this->db["데미지"]) || $id === 0 and $data === 0) {
                    return;
                }
                $x = $this->db["감옥"] [$a[0]] ["x"];
                $y = $this->db["감옥"] [$a[0]] ["y"];
                $z = $this->db["감옥"] [$a[0]] ["z"];
                $lv = $this->db["감옥"] [$a[0]] ["lv"];
                $player->teleport(new Position($x, $y, $z, $this->getServer()->getLevelByName($lv)), $player->getYaw(), $player->getPitch());
                $player->addActionBarMessage("§c풀려나기까지 남은 시간: {$a[1]}분");
            }
        }
    }
    public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event) {
    	$player = $event->getPlayer();
        $name = strtolower($player->getName());
        if (! isset($this->db[strtolower($event->getPlayer()->getName())])) {
            return;
        } else {
            if (! $player->isOp()) {
                $event->setCancelled(true);
                $this->msg($event->getPlayer(), "감옥에 있을때는 명령어를 사용할수 없습니다");
            } else {
                $event->setCancelled(false);
            }
        }
    }
    public function msg($player, $msg) {
        $player->sendMessage("§b§l[ §f감옥 §b] §r" . $msg);
    }
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if ($command->getName() === "감옥") {
            if (! $sender->isOp()) {
                $sender->sendMessage("권한이 부족합니다");
                return true;
            }
            if (! isset($args[0])) {
                $this->msg($sender, "/감옥 수감 <닉네임> <감옥번호> <시간>");
                $this->msg($sender, "/감옥 석방 <닉네임>");
                $this->msg($sender, "/감옥 블럭설정");
                $this->msg($sender, "/감옥 설정 번호");
                return true;
            }
            if ($args[0] === "수감") {
                if (! isset($args[1])) {
                    $this->msg($sender, "닉네임을 적어주세요");
                    return true;
                }
                if (! file_exists($this->getServer()->getDataPath() . "players/" . strtolower($args[1]) . ".dat")) {
                    $this->msg($sender, "그런 플레이어는 서버에 접속한적이 없습니다");
                    return true;
                }
                $n = explode(":", $this->db[strtolower($args[1])] ["감옥"]);
                if (! isset($args[2])) {
                    $this->msg($sender, "감옥 번호를 입력해주세요");
                    return true;
                }
                if (! isset($args[3])) {
                    $this->msg($sender, "시간을 입력해주세요");
                    return true;
                }
                if ($n[1] > 0) {
                    $this->msg($sender, "해당 플레이어는 이미 감옥에 가있습니다");
                    return true;
                }
                $this->db[strtolower($args[1])] ["감옥"] = $args[2] . ":" . $args[3];
                $this->save();
                $this->msg($sender, "{$args[1]} 플레이어를 {$args[2]} 번 감옥에 {$args[3]} 분 동안 가두었습니다");
            }
            if ($args[0] === "석방") {
                if (! isset($args[1])) {
                    $this->msg($sender, "닉네임을 입력해주세요");
                    return true;
                }
                $n = explode(":", $this->db[strtolower($args[1])] ["감옥"]);
                if ($n[1] === 0) {
                    $this->msg($sender, "해당 플레이어는 감옥에 가있지 않습니다");
                    return true;
                }
                $this->db[strtolower($args[1])] ["감옥"] = 0 . ":" . 0;
                $this->save();
                $this->msg($sender, "{$args[1]} 플레이어를 석방했습니다");
                $target = $this->getServer()->getPlayerExact($args[1]);
                if ($target->isOnline()) {
                    $this->msg($target, "석방되었습니다");
                }
            }
            if ($args[0] === "블럭설정") {
                $x = (int) round($sender->x - 0.5);
                $y = (int) round($sender->y - 1);
                $z = (int) round($sender->z - 0.5);
                $id = $sender->getLevel()->getBlockIdAt($x, $y, $z);
                $data = $sender->getLevel()->getBlockDataAt($x, $y, $z);
                $this->db["코드"] = $id;
                $this->db["데미지"] = $data;
                $this->save();
                $this->msg($sender, "설정이 완료되었습니다");
            }
            if ($args[0] === "설정") {
                if (! isset($args[1]) or ! is_numeric($args[1])) {
                    $this->msg($sender, "번호로 입력해주세요");
                    return true;
                }
                $this->db["감옥"] [$args[1]] ["x"] = $sender->x;
                $this->db["감옥"] [$args[1]] ["y"] = $sender->y;
                $this->db["감옥"] [$args[1]] ["z"] = $sender->z;
                $this->db["감옥"] [$args[1]] ["lv"] = $sender->getLevel()->getFolderName();
                $this->save();
                $this->msg($sender, "설정이 완료되었습니다");
            }
        }
        return true;
    }
    public function save() {
        $this->config->setAll($this->db);
        $this->config->save();
    }
    public function Prison(Player $player) {
        $name = strtolower($player->getName());
        if (! isset($this->db[$name] ["감옥"])) {
            return;
        }
        $a = explode(":", $this->db[$name] ["감옥"]);
        if ($a[1] > 0) {
            $b = $a[1] - 1;
            $this->db[$name] ["감옥"] = $a[0] . ":" . $b;
            unset($b);
            $a = explode(":", $this->db[$name] ["감옥"]);
            $min = (int) ($a[1]/60);
            $sec = $a[1]%60;
            if ($a[1] <= 0) {
                $this->msg($player, "이제 스폰 명령어를 쳐서 돌아가주세요");
                $this->db[$name] ["감옥"] = 0 . ":" . 0;
                $this->getLogger()->info($name . " 플레이어 석방됨.");
            }
        }
    }
}
class PrisonTask extends Task{
    public function __construct(Prison $owner) {
        $this->owner = $owner;
    }
    public function onRun(int $currentTick) {
        foreach($this->owner->getServer()->getOnlinePlayers() as $player) {
            $this->owner->Prison($player);
        }
        $this->owner->save();
    }
}

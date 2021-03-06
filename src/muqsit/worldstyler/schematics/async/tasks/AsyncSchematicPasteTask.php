<?php

declare(strict_types=1);
namespace muqsit\worldstyler\schematics\async\tasks;

use muqsit\worldstyler\schematics\Schematic;
use muqsit\worldstyler\shapes\async\tasks\AsyncChunksChangeTask;

use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\Server;

class AsyncSchematicPasteTask extends AsyncChunksChangeTask {

    /** @var Vector3 */
    private $relative_pos;

    /** @var string */
    private $file;

    /** @var bool */
    private $replace_pc_blocks;

    public function __construct(Vector3 $relative_pos, string $file, bool $replace_pc_blocks = true, ?callable $callable)
    {
        $this->relative_pos = $relative_pos;
        $this->file = $file;
        $this->replace_pc_blocks = $replace_pc_blocks;
        $this->setCallable($callable);
    }

    public function onRun() : void
    {
        $schematic = new Schematic($this->file);
        $schematic->load();
        $width = $schematic->getWidth();
        $length = $schematic->getLength();
        $this->publishProgress([$width, $length]);

        while ($this->chunks === null);

        $level = $this->getChunkManager();
        $rel_pos = $this->relative_pos;
        $schematic->paste($level, $rel_pos, $this->replace_pc_blocks, [$this, "updateStatistics"]);
        $schematic->invalidate();

        $this->saveChunks($level, $rel_pos, $rel_pos->add($width, 0, $length));
    }

    public function onProgressUpdate(Server $server, $progress) : void
    {
        $level = $server->getLevel($this->levelId);
        [$width, $length] = $progress;

        $chunks = [];

        $rel_pos = $this->relative_pos;
        $relx = $rel_pos->x;
        $rely = $rel_pos->y;
        $relz = $rel_pos->z;

        for ($x = 0; $x < $width; ++$x) {
            $chunkX = ($x + $relx) >> 4;
            for ($z = 0; $z < $length; ++$z) {
                $chunkZ = ($z + $relz) >> 4;
                $chunks[Level::chunkHash($chunkX, $chunkZ)] = $level->getChunk($chunkX, $chunkZ, true);
            }
        }

        $this->setChunks($chunks);
    }
}
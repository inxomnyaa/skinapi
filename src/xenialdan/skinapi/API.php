<?php

namespace xenialdan\skinapi;

use pocketmine\entity\Skin;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

/**
 * Class API
 * @package xenialdan\skinapi
 */
class API{

	/**
	 * TODO
	 */
	const MULTIPLIER_LEFT = [];
	const MULTIPLIER_RIGHT = [];
	const MULTIPLIER_TOP = [];
	const MULTIPLIER_BOTTOM = [];
	const MULTIPLIER_FRONT = [];
	const MULTIPLIER_BACK = [];

	/**
	 * @param Skin $skin
	 * @return array
	 */
	public static function jsonSerialize(Skin $skin){
		return [
			"skinId" => $skin->getSkinId(),
			"skinData" => $skin->getSkinData(),
			"capeData" => $skin->getCapeData(),
			"geometryName" => $skin->getGeometryName(),
			"geometryData" => $skin->getGeometryData(),
		];
	}

	/**
	 * @param Skin $skin
	 * @param string $path
     * @throws \InvalidArgumentException
	 */
	public static function saveSkin(Skin $skin, string $path){
        if (!extension_loaded("gd")) {
            Server::getInstance()->getLogger()->error("GD library is not enabled! Please uncomment gd2 in php.ini!");
        }
        if (strpos($path,".png") === false) {
            throw new \InvalidArgumentException("Missing a filename in the path");
        }
		$config = new Config($path . 'skin.json');
		$config->setAll([$skin->getSkinId() => [$skin->getSkinData(), $skin->getGeometryData()]]);
		$config->save();
		$img = self::toImage($skin->getSkinData());
		imagepng($img, $path);
	}

	/**
	 * @param Player $player
	 * @param string $path
	 */
	public static function saveSkinOfPlayer(Player $player, string $path){
		self::saveSkin($player->getSkin(), $path);
	}

	/**
	 * @param array ...$parts
	 * @return mixed
	 */
	public static function mergeParts(...$parts){
        if (!extension_loaded("gd")) {
            Server::getInstance()->getLogger()->error("GD library is not enabled! Please uncomment gd2 in php.ini!");
        }
		$baseimg = $parts[0];
		$base = imagecreatetruecolor(imagesx($baseimg), imagesy($baseimg));
		#imagealphablending($base, false);
		imagesavealpha($base, true);
		foreach ($parts as $part){
			$img = $part;
			imagecopy($base, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
		}
		return $base;
	}

	/**
	 * NOTICE: Only merges the images, not the json data!
	 * @param Skin[] $skins
	 * @return resource
	 */
	public static function mergeSkinsToImage(...$skins){
		$baseskin = $skins[0];
		$baseimg = self::toImage($baseskin->getSkinData());
		$base = imagecreatetruecolor(imagesx($baseimg), imagesy($baseimg));
		imagesavealpha($base, true);
		imagefill($base, 0, 0, imagecolorallocatealpha($base, 0, 0, 0, 127));
		foreach ($skins as $skin){
			$img = self::toImage($skin->getSkinData());
			imagecopy($base, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
		}
		return $base;
	}

	/**
	 * NOTICE: Only merges the images, not the json data!
	 * @param string[] $skinDataSets
	 * @return string
	 */
	public static function mergeSkinData(...$skinDataSets){
		$baseskin = $skinDataSets[0];
		$baseimg = self::toImage($baseskin);
		$base = imagecreatetruecolor(imagesx($baseimg), imagesy($baseimg));
		imagesavealpha($base, true);
		imagefill($base, 0, 0, imagecolorallocatealpha($base, 0, 0, 0, 127));
		foreach ($skinDataSets as $skinData){
			$img = self::toImage($skinData);
			imagecopy($base, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
		}
		return self::fromImage($base);
	}

	/**
	 * @param Skin $skin
	 * @param bool $showhat
	 * @return mixed
	 */
	public static function getHead(Skin $skin, $showhat = false){
		$head = self::getPart($skin, 'head');
		if ($showhat){
			return self::mergeParts($head, self::getPart($skin, 'hat'));
		}
		return self::mergeParts($head);
	}

	/**
	 * @param Skin $skin
	 * @param $partname
	 * @param array $side
	 * @return resource
	 */
	public static function getPart(Skin $skin, $partname, $side = self::MULTIPLIER_FRONT){//TODO SIDE
		$skindata = $skin->getSkinData();
		$img = self::toImage($skindata);
		imagealphablending($img, false);
		imagesavealpha($img, true);
		$skingeometry = $skin->getGeometryData();
		$son = json_decode($skingeometry, true);
		/* Head */
		$res = self::search($son, 'name', $partname);
		$partgeometry = $res[0];
		$startpos = $partgeometry["cubes"][0]["uv"];//0x 1y
		$size = $partgeometry["cubes"][0]["size"];//0x 1y 2z
		$startpos[0] = $startpos[0] + $size[2];//add the depth of the head because the left side comes first
		$startpos[1] = $startpos[1] + $size[1];//add the height of the head because the top comes first
		$part = imagecreatetruecolor($size[0], $size[1]);//create helmet of the size of the front//TODO fix correct sides
		imagealphablending($part, false);
		imagesavealpha($part, true);
		imagecopy($part, $img, 0, 0, $startpos[0], $startpos[1], $size[0], $size[1]);
		return $part;
	}

	/**
	 * @param Skin $skin
	 * @param $json
	 * @return string
	 */
	public static function addJSONtoExistingSkin(Skin $skin, $json){
		$skingeometry = $skin->getGeometryData();
		$base = json_decode($skingeometry, true);
		$json = str_replace('%s', $skin->getGeometryName(), $json);
		$extension = self::json_clean_decode($json, true);
		$finished = json_encode(array_merge($base, $extension));
		return $finished;
	}

	/**
	 * @param $data
	 * @param int $height
	 * @param int $width
	 * @return resource
	 */
	public static function toImage($data, $height = 64, $width = 64){
        if (!extension_loaded("gd")) {
            Server::getInstance()->getLogger()->error("GD library is not enabled! Please uncomment gd2 in php.ini!");
        }
		$pixelarray = str_split(bin2hex($data), 8);
		$image = imagecreatetruecolor($width, $height);
		imagealphablending($image, false);//do not touch
		imagesavealpha($image, true);
		$position = count($pixelarray) - 1;
		while (!empty($pixelarray)){
			$x = $position % $width;
			$y = ($position - $x) / $height;
			$walkable = str_split(array_pop($pixelarray), 2);
			$color = array_map(function ($val){ return hexdec($val); }, $walkable);
			$alpha = array_pop($color); // equivalent to 0 for imagecolorallocatealpha()
			$alpha = ((~((int)$alpha)) & 0xff) >> 1; // back = (($alpha << 1) ^ 0xff) - 1
			array_push($color, $alpha);
			imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, ...$color));
			$position--;
		}
		return $image;
	}

	/**
	 * @param resource $img
	 * @return string
	 */
	public static function fromImage($img){
        if (!extension_loaded("gd")) {
            Server::getInstance()->getLogger()->error("GD library is not enabled! Please uncomment gd2 in php.ini!");
        }
        $bytes = '';
		for ($y = 0; $y < imagesy($img); $y++){
			for ($x = 0; $x < imagesx($img); $x++){
                $rgba = @imagecolorat($img, $x, $y);
                $a = ((~((int)($rgba >> 24))) << 1) & 0xff;
                $r = ($rgba >> 16) & 0xff;
                $g = ($rgba >> 8) & 0xff;
                $b = $rgba & 0xff;
                $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
			}
		}
		return $bytes;
	}

	/**
	 * @param $array
	 * @param $key
	 * @param $value
	 * @return array
	 */
	public static function search($array, $key, $value){
		$results = array();
		self::search_r($array, $key, $value, $results);
		return $results;
	}

	/**
	 * @param $array
	 * @param $key
	 * @param $value
	 * @param $results
	 */
	public static function search_r($array, $key, $value, &$results){
		if (!is_array($array)){
			return;
		}

		if (isset($array[$key]) && $array[$key] == $value){
			$results[] = $array;
		}

		foreach ($array as $subarray){
			self::search_r($subarray, $key, $value, $results);
		}
	}

	/**
	 * @param $json
	 * @param bool $assoc
	 * @param int $depth
	 * @param int $options
	 * @return mixed
	 */
	public static function json_clean_decode($json, $assoc = false, $depth = 512, $options = 0){
		// search and remove comments like /* */ and //
		$json = preg_replace("#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#", '', $json);
		if (version_compare(phpversion(), '5.4.0', '>=')){
			return json_decode($json, $assoc, $depth, $options);
		} elseif (version_compare(phpversion(), '5.3.0', '>=')){
			return json_decode($json, $assoc, $depth);
		} else{
			return json_decode($json, $assoc);
		}
	}
}
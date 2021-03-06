<?php
namespace jens1o\smashcast\user\logo;

use jens1o\smashcast\model\IDownloadable;
use jens1o\smashcast\SmashcastApi;
use jens1o\util\HttpMethod;

/**
 * Represents a logo that can be downloaded or just refers to a link
 *
 * @author     jens1o
 * @copyright  Jens Hausdorf 2017
 * @license    MIT License
 * @package    jens1o\smashcast\user
 * @subpackage logo
 */
class Logo implements IDownloadable {

    /**
     * Holds the url where this logo is saved on
     * @var string
     */
    private $url = null;

    /**
     * Cached stream of the logo
     * @var string
     */
    private $stream = null;

    /**
     * Creates a new logo representation
     *
     * @param   string  $url    The uri (without the host) the image is hosted on
     */
    public function __construct(string $url) {
        $this->url = $url;
    }

    /**
     * @inheritDoc
     */
    public function download(string $location): bool {
        $stream = $this->getStream();

        // couldn't download it
        if($stream === null) {
            return false;
        }

        if(file_put_contents($location, $stream) === false) {
            // couldn't write it
            return false;
        }

        // yada yada yada!
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getStream(): ?string {
        if(null !== $this->stream) {
            return $this->stream;
        }

        try {
            $stream = SmashcastApi::getClient()
                ->request(HttpMethod::GET, $this->getPath())
                ->getBody()
                ->getContents();
        } catch(\Throwable $e) {
            // rethrow exception
            throw new SmashcastApiException('Cannot download the logo!', 0, $e);
            return null;
        }

        $this->stream = $stream;

        return $stream;
    }

    /**
     * Returns the path of this logo
     * @var string
     */
    public function getPath(): string {
        return SmashcastApi::IMAGE_URL . $this->url;
    }

    /**
     * @see getPath()
     */
    public function __toString(): string {
        return $this->getPath();
    }

}
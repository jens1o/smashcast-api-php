<?php
namespace jens1o\smashcast\channel;

use jens1o\smashcast\SmashcastApi;
use jens1o\smashcast\emoji\SmashcastChannelEmojis;
use jens1o\smashcast\exception\SmashcastApiException;
use jens1o\smashcast\media\live\SmashcastLiveMedia;
use jens1o\smashcast\util\RequestUtil;
use jens1o\util\HttpMethod;

/**
 * Represents a channel which can host other channels, is decorated with videos, has a chat...
 * Note: This class relies on that you have set an auth token yourself, because mostly we
 * need to send it right into the body
 *
 * @author     jens1o
 * @copyright  Jens Hausdorf 2017
 * @license    MIT License
 * @package    jens1o\smashcast
 * @subpackage channel
 */
class SmashcastChannel {

    /**
     * Holds the channel name
     * @var string
     */
    private $channelName;

    /**
     * Holds the cached editor list
     * @var \stdClass[]
     */
    private $editorList;

    /**
     * Holds the cached list of channels hosting this channel
     * @var \stdClass[]
     */
    private $hostingList;

    /**
     * Holds the instance of this channel's live media
     * @var SmashcastLiveMedia
     */
    private $liveMedia;

    /**
     * Holds the instance of the emojis of this channel
     * @var SmashcastChatEmojis
     */
    private $chatEmojis;

    /**
     * Holds how many views this channel has
     * @var int
     */
    private $totalViews;

    /**
     * Creates a new channel object based on the name.
     *
     * @param   string  $identifier     The identifier for the name
     */
    public function __construct(string $identifier) {
        $this->channelName = strtolower($identifier);
    }

    /**
     * Returns the live media for this channel
     *
     * @return SmashcastLiveMedia
     */
    public function getLiveMedia(): SmashcastLiveMedia {
        if($this->liveMedia === null) {
            $this->liveMedia = new SmashcastLiveMedia($this->channelName);
        }

        return $this->liveMedia;
    }

    /**
     * Returns the chat emojis for this channel
     *
     * @return SmashcastChannelEmojis
     */
    public function getChatEmojis(): SmashcastChannelEmojis {
        if($this->chatEmojis === null) {
            $this->chatEmojis = new SmashcastChannelEmojis($this->channelName);
        }

        return $this->chatEmojis;
    }

    /**
     * Shortcut function to get the time the user has been registered.
     * Returns null when there was an error parsing the date
     *
     * @return \DateTime|null
     */
    public function getTimeCreated(): ?\DateTime {
        return $this->getLiveMedia()->getTimeCreated();
    }

    /**
     * Returns the channel name
     *
     * @return string
     */
    public function __toString() {
        return $this->getChannelName();
    }

    /**
     * Returns the channel name
     *
     * @return string
     */
    public function getChannelName(): string {
        return $this->channelName;
    }

    /**
     * Returns the stream key for this channel, or null when an error occurred.
     * Note: This returns the plain key. You need to prepend the channel name and `?key=`.
     * Example: jens1o?key=V2YOpgLH [Tip: This token is invalid.]
     *
     * @return string|null
     */
    public function getStreamKey(): ?string {
        try {
            $response = RequestUtil::doRequest(HttpMethod::GET, 'mediakey/' . $this->channelName, [
                'appendAuthToken' => false,
            ], true);
        } catch(SmashcastApiException $e) {
            return null;
        }

        if(isset($response->streamKey)) {
            return $response->streamKey;
        }

        return null;
    }

    /**
     * Resets the stream key for the channel. Do not test this while streaming. Returns null on failure, and new key as a string when successful
     * Note: This returns the plain key. You need to prepend the channel name and `?key=`.
     * Example: jens1o?key=V2YOpgLH [Tip: This token is invalid.]
     *
     * @return string|null
     */
    public function resetStreamKey(): ?string {
        try {
            $response = RequestUtil::doRequest(HttpMethod::PUT, 'mediakey/' . $this->channelName, [
                'appendAuthToken' => false
            ], true);
        } catch(SmashcastApiException $e) {
            return null;
        }

        if(isset($response->streamKey)) {
            return $response->streamKey;
        }

        return null;
    }

    /**
     * Clears the cache of the editor list and the list of channels hosting this channel. Returns the same instance.
     *
     * @return SmashcastChannel
     */
    public function invalidateCache(): SmashcastChannel {
        $this->editorList = null;
        $this->hostingChannels = null;
        return $this;
    }

    /**
     * Returns the list of editors for this channel or null when an error occurred.
     * Note: This can only be executed by the channel admin.
     *
     * @param   $skipCache  Whether to skip the cache or not.
     * @return mixed[]|null
     */
    public function getEditors(bool $skipCache = false): ?array {
        if(!$skipCache && null !== $this->editorList) {
            return $this->editorList;
        }

        try {
            $response = RequestUtil::doRequest(HttpMethod::GET, 'editors/' . $this->channelName, [
                'appendAuthToken' => false
            ], true);
        } catch(SmashcastApiException $e) {
            return null;
        }

        if(isset($response->list)) {
            // update cache
            $this->editorList = $response->list;
            return $response->list;
        }

        return null;
    }

    /**
     * Returns true when `$userName` is an editor in this channel.
     *
     * @param   string  $userName   The username you want to check
     * @return bool
     */
    public function isEditor(string $userName): bool {
        $editors = $this->getEditors();
        // do this here, this improves MUCH the performance than doing it on every call!
        $userName = strtolower($userName);

        foreach($editors as $editor) {
            if(strtolower($editor->user_name) === $userName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds `$username` as an editor to this channel. Returns whether the action has been completed successfully.
     *
     * @param   string  $userName The name of the user you want to add as an editor.
     * @return bool
     * @throws \BadMethodCallException
     */
    public function addEditor(string $userName): bool {
        if($this->channelName === strtolower($userName)) {
            throw new \BadMethodCallException('You may not want to add yourself as an editor!');
            return false;
        }

        if($this->isEditor($userName)) {
            throw new \BadMethodCallException($userName . ' is already an editor in this channel!');
            return false;
        }

        try {
            $response = RequestUtil::doRequest(HttpMethod::POST, 'editors/' . $this->channelName, [
                'json' => [
                    'authToken' => SmashcastApi::getUserAuthToken()->getToken(),
                    'editor' => $userName,
                    'remove' => false
                ],
                'appendAuthToken' => false
            ], true);
        } catch(SmashcastApiException $e) {
            return false;
        }

        if(isset($response->message) && $response->message === 'success') {
            // update cache
            $this->invalidateCache();
            return true;
        }

        return false;
    }

    /**
     * Removes `$userName` existence of being an editor in this channel. Returns whether the action has been completed successfully.
     * Warning: This can produce fights! I'd warned you!
     *
     * @param   string  $userName   The name of the user you want to remove as an editor.
     * @return bool
     * @throws \BadMethodCallException
     */
    public function removeEditor(string $userName): bool {
        if($this->channelName === strtolower($userName)) {
            throw new \BadMethodCallException('You may not want to remove yourself as an editor!');
            return false;
        }

        if(!$this->isEditor($userName)) {
            throw new \BadMethodCallException($userName . ' is not an editor in this channel!');
            return false;
        }

        try {
            $response = RequestUtil::doRequest(HttpMethod::POST, 'editors/' . $this->channelName, [
                'json' => [
                    'authToken' => SmashcastApi::getUserAuthToken()->getToken(),
                    'editor' => $userName,
                    'remove' => true
                ],
                'appendAuthToken' => false
            ], true);
        } catch(SmashcastApiException $e) {
            return false;
        }

        if(isset($response->message) && $response->message === 'success') {
            // update cache
            $this->invalidateCache();
            return true;
        }

        return false;
    }

    /**
     * Toggles the existence of `$userName` being an editor in this channel.
     * Returns an array with two keys:
     *  * success [bool] describing the success of the action
     *  * action [enum `removed` or `added`] describing what happened.
     *
     * @param   string  $userName   Which user do you like to toggle?
     * @return array
     * @throws \BadMethodCallException
     */
    public function toggleEditor(string $userName): array {
        if($this->isEditor($userName)) {
            return [
                'success' => $this->removeEditor($userName),
                'action' => 'removed'
            ];
        }

        return [
            'success' => $this->addEditor($userName),
            'action' => 'added'
        ];
    }

    /**
     * Sends a tweet to the twitter account of this channel. Returns whether the action was successfully executed.
     *
     * @param   string  $message    The message you want to send, remember you need to know the character limit!
     * @return bool
     * @throws \InvalidArgumentException When the tweet is too long.
     */
    public function sendTweet(string $message): bool {
        static $suffix = ' via @smashcast_tv';
        static $tweetLength = 144;

        if(strlen($message . $suffix) > $tweetLength) {
            throw new \InvalidArgumentException('The message MUST NOT be longer than ' . $tweetLength . ' chars(even when appending the Smashcast suffix)! Length: ' . strlen($message . $suffix));
            return false;
        }

        try {
            $response = RequestUtil::doRequest(HttpMethod::POST, 'twitter/post', [
                'json' => [
                    'authToken' => SmashcastApi::getUserAuthToken()->getToken(),
                    'user_name' => $this->channelName,
                    'message' => $message
                ],
                'query' => [
                    'user_name' => $this->channelName
                ],
                'appendAuthToken' => false
            ], true);
        } catch(SmashcastApiException $e) {
            return false;
        }

        if(isset($response->message) && $response->message === 'success') {
            return true;
        }

        return false;
    }

    /**
     * Sends a facebook post to the account of this channel. Returns whether the action was successfully executed.
     *
     * @param   string  $message    The message you want to send, remember you need to know the character limit!
     * @return bool
     * @throws \InvalidArgumentException When the tweet is too long.
     */
    public function sendFacebookPost(string $message): bool {
        // Facebook max post length is around 64k, we do not need to check this :D
        try {
            $response = RequestUtil::doRequest(HttpMethod::POST, 'facebook/post', [
                'json' => [
                    'authToken' => SmashcastApi::getUserAuthToken()->getToken(),
                    'user_name' => $this->channelName,
                    'message' => $message
                ],
                'query' => [
                    'user_name' => $this->channelName
                ],
                'appendAuthToken' => false
            ], true);
        } catch(SmashcastApiException $e) {
            return false;
        }

        if(isset($response->message) && $response->message === 'success') {
            return true;
        }

        return false;
    }

    /**
     * Returns a list of channels hosting this channel or null when an error occurred.
     * Note: There is a difference between `[]`(array) and `null`(not a string)
     *
     * @param   bool    $skipCache  Whether to skip cache or not.
     * @return \stdClass[]|null
     */
    public function getHostingChannels(bool $skipCache = false): ?array {
        if(!$skipCache && null !== $this->hostingChannels) {
            return $this->hostingChannels;
        }

       try {
            $response = RequestUtil::doRequest(HttpMethod::GET, 'hosters/' . $this->channelName, [
                'appendAuthToken' => false
            ], true);
       } catch(SmashcastApiException $e) {
           return null;
       }

       if(isset($response->hosters)) {
           $this->hostingChannels = $response->hosters;
           return $response->hosters;
       }

       return null;
    }

    /**
     * Returns true when `$userName` hosts this channel, false when it doesn't
     *
     * @param   string  $userName   The name of the user you want to check
     * @return bool
     */
    public function isHoster(string $userName): bool {
        $hosters = $this->getHostingChannels();
        // do this here, this improves MUCH the performance than doing it on every call!
        $userName = strtolower($userName);

        foreach($hosters as $hoster) {
            if(strtolower($hoster->user_name) === $userName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns how many views this channel has, null on failure.
     *
     * Note: The Smashcast API returns `false` when the channel has zero views. This client
     * still returns `0` then!
     *
     * @param   bool    $skipCache  When true, you get a fresh result ;)
     * @return int|null
     */
    public function getTotalViews(bool $skipCache = false): ?int {
        // always retry on failure
        if(!$skipCache && null !== $this->totalViews) {
            return $this->totalViews;
        }

        try {
            $response = RequestUtil::doRequest(HttpMethod::GET, 'media/views/' . $this->channelName, ['noAuthToken' => true]);
        } catch(SmashcastApiException $e) {
            $this->totalViews = null;
            return null;
        }

        if(isset($response->total_live_views) && null !== $response->total_live_views) {
            // when `$response->total_live_views` is `false`, it gets a `0`
            $this->totalViews = (int) $response->total_live_views;
            return $this->totalViews;
        }

        $this->totalViews = null;
        return null;
    }

    // TODO: Get a person which tests running ads. Or can I do that myself?

}
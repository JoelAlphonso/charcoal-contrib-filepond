<?php

namespace Charcoal\FilePond\Action;

// from 'charcoal-contrib-filepond'
use Charcoal\FilePond\Service\Helper\Transfer;
use Charcoal\FilePond\Service\FilePondService;

// from charcoal-app
use Charcoal\App\Action\AbstractAction;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Stream;

/**
 * Class RequestAction
 */
class RequestAction extends AbstractAction
{
    /**
     * @var FilePondService $filePondService
     */
    private $filePondService;

    const HANDLERS = [
        'FILE_TRANSFER'        => 'handleFileTransfer',
        'REVERT_FILE_TRANSFER' => 'handleRevertFileTransfer',
        'FILE_LOAD'            => 'handleFileLoad',
    ];

    /**
     * Section constructor.
     * @param array $data Init data.
     */
    public function __construct(array $data = null)
    {
        parent::__construct($data);

        $this->filePondService  = $data['filePondService'];
    }

    /**
     * Gets a psr7 request and response and returns a response.
     *
     * Called from `__invoke()` as the first thing.
     *
     * @param RequestInterface  $request  A PSR-7 compatible Request instance.
     * @param ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        $handler = $this->filePondService->parseApiRequest($request);

        if (array_key_exists($handler['ident'], self::HANDLERS)) {
            return call_user_func(
                [$this, self::HANDLERS[$handler['ident']]],
                $handler['data'],
                $response
            );
        }

        $this->setSuccess(true);

        return $response;
    }

    /**
     * @param Transfer          $transfer Transfer object.
     * @param ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    private function handleFileTransfer(Transfer $transfer, ResponseInterface $response)
    {
        $this->setMode('custom');
        $files = $transfer->getFiles();
        // something went wrong, most likely a field name mismatch
        if (count($files) === 0) {
            $this->setSuccess(false);

            return $response->withStatus(400);
        }
        // store files
        $this->filePondService->storeTransfer($this->filePondService->getServer()->transferDir(), $transfer);

        // returns plain text content
        $response->getBody()->write($transfer->getId());

        // remove item from array Response contains uploaded file server id
        return $response->withHeader('Content-Type', 'text/plain');
    }

    /**
     * @param string            $id       FilePond hash.
     * @param ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    private function handleRevertFileTransfer($id, ResponseInterface $response)
    {
        // test if id was supplied
        if (!isset($id) || !$this->filePondService->isValidTransferId($id)) {
            return $response->withStatus(400);
        }

        // remove transfer directory
        $this->filePondService->removeTransferDirectory($this->filePondService->getServer()->transferDir(), $id);

        // no content to return
        return $response->withStatus(204);
    }

    /**
     * @param string|array      $file     The file path.
     * @param ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    private function handleFileLoad($file, ResponseInterface $response)
    {
        // read file object
        if (is_string($file)) {
            $file = $this->filePondService->readFile($file);
        }
        // something went wrong while reading the file
        if (!$file) {
            $this->setSuccess(false);

            return $response->withStatus(500);
        }

        // if (!$file || empty($file)) {}

        $this->setMode('inline');
        $stream = new Stream($file['content']);
        $disposition = $this->generateHttpDisposition('inline', $file['name']);

        // Allow to read Content Disposition (so we can read the file name on the client side)
        return $response
            ->withHeader('Access-Control-Expose-Headers', 'Content-Disposition, Content-Length, X-Content-Transfer-Id')
            ->withHeader('Content-Type', $file['type'])
            ->withHeader('Content-Length', $file['length'])
            ->withHeader('Content-Disposition', $disposition)
            ->withBody($stream);
    }

    /**
     * Generates a HTTP 'Content-Disposition' field-value.
     *
     * Note: Adapted from Symfony\HttpFoundation.
     *
     * @see   https://github.com/symfony/http-foundation/blob/master/LICENSE
     *
     * @see   RFC 6266
     * @param string $disposition      Either "inline" or "attachment".
     * @param string $filename         A unicode string.
     * @param string $filenameFallback A string containing only ASCII characters that
     *                                 is semantically equivalent to $filename. If the filename is already ASCII,
     *                                 it can be omitted, or just copied from $filename.
     * @throws InvalidArgumentException If the parameters are invalid.
     * @return string A string suitable for use as a Content-Disposition field-value.
     */
    public function generateHttpDisposition($disposition, $filename, $filenameFallback = '')
    {
        if ($filenameFallback === '') {
            $filenameFallback = $filename;
        }

        // percent characters aren't safe in fallback.
        if (strpos($filenameFallback, '%') !== false) {
            throw new InvalidArgumentException('The filename fallback cannot contain the "%" character.');
        }

        // path separators aren't allowed in either.
        if (strpos($filename, '/') !== false ||
            strpos($filename, '\\') !== false ||
            strpos($filenameFallback, '/') !== false ||
            strpos($filenameFallback, '\\') !== false) {
            throw new InvalidArgumentException(
                'The filename and the fallback cannot contain the "/" and "\\" characters.'
            );
        }

        $output = sprintf('%s; filename="%s"', $disposition, str_replace('"', '\\"', $filenameFallback));

        if ($filename !== $filenameFallback) {
            $output .= sprintf("; filename*=utf-8''%s", rawurlencode($filename));
        }

        return $output;
    }

    /**
     * Returns an associative array of results (set after being  invoked / run).
     *
     * The raw array of results will be called from `__invoke()`.
     *
     * @return array|mixed
     */
    public function results()
    {
        return '';
    }
}

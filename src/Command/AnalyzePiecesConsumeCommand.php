<?php

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfBorderFinderContext;
use Bywulf\Jigsawlutioner\Dto\DerivativePoint;
use Bywulf\Jigsawlutioner\Exception\BorderParsingException;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\Exception\SideParsingException;
use Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder;
use Bywulf\Jigsawlutioner\Service\PieceAnalyzer;
use Bywulf\Jigsawlutioner\Service\SideFinder\ByWulfSideFinder;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;
use Bywulf\Jigsawlutioner\Util\PieceLoaderTrait;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:consumer:piece:analyze')]
class AnalyzePiecesConsumeCommand extends Command
{
    use PieceLoaderTrait;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        $channel->basic_qos(null, 1, null);
        $channel->basic_consume('analyze', 'consumer', false, false, false, false, [$this, 'process']);

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        return self::SUCCESS;
    }

    public function process(AMQPMessage $message): void
    {
        $content = json_decode($message->body, true);

        $borderFinder = new ByWulfBorderFinder();
        $sideFinder = new ByWulfSideFinder();
        $pieceAnalyzer = new PieceAnalyzer($borderFinder, $sideFinder);

        $meta = json_decode(file_get_contents(__DIR__ . '/../../resources/Fixtures/Set/' . $content['setName'] . '/meta.json'), true);

        $image = imagecreatefromjpeg(__DIR__ . '/../../resources/Fixtures/Set/' . $content['setName'] . '/piece' . $content['pieceNumber'] . '.jpg');
        $transparentImage = imagecreatefromjpeg(__DIR__ . '/../../resources/Fixtures/Set/' . $content['setName'] . '/piece' . $content['pieceNumber'] . ($meta['separateColorImages'] ?? false ? '_color' : '') . '.jpg');

        $resizedImage = imagecreatetruecolor((int) round(imagesx($transparentImage) / 10), (int) round(imagesy($transparentImage) / 10));
        imagecopyresampled($resizedImage, $transparentImage, 0, 0, 0,0, (int) round(imagesx($transparentImage) / 10), (int) round(imagesy($transparentImage) / 10), imagesx($transparentImage), imagesy($transparentImage));

        try {
            $piece = $pieceAnalyzer->getPieceFromImage($content['pieceNumber'], $image, new ByWulfBorderFinderContext(
                threshold: $meta['threshold'],
                transparentImage: $transparentImage,
                smallTransparentImage: $resizedImage,
            ));

            // Found corners
            $black = imagecolorallocate($image, 0, 0, 0);
            foreach ($piece->getBorderPoints() as $point) {
                if ($point instanceof DerivativePoint && $point->isUsedAsCorner()) {
                    for ($x = (int) $point->getX() - 10; $x < $point->getX() + 10; ++$x) {
                        imagesetpixel($image, $x, (int) $point->getY(), $black);
                    }
                    for ($y = (int) $point->getY() - 10; $y < $point->getY() + 10; ++$y) {
                        imagesetpixel($image, (int) $point->getX(), $y, $black);
                    }
                }
            }

            // BorderPoints (red = anti-clockwise, green = clockwise)
            foreach ($piece->getBorderPoints() as $point) {
                $color = imagecolorallocate($image, 255, 255, 255);
                if ($point instanceof DerivativePoint) {
                    $diff = (int) min((abs($point->getDerivative()) / 90) * 255, 255);

                    $color = imagecolorallocate(
                        $image,
                        255 - ($point->getDerivative() > 0 ? $diff : 0),
                        255 - ($point->getDerivative() < 0 ? $diff : 0),
                        255 - $diff
                    );
                    if ($point->isExtreme()) {
                        $color = imagecolorallocate($image, 255, 255, 0);
                    }
                }
                imagesetpixel($image, (int) $point->getX(), (int) $point->getY(), $color);
            }

            // Smoothed and normalized side points
            $resizeFactor = 3;
            foreach ($piece->getSides() as $sideIndex => $side) {
                foreach ($side->getPoints() as $point) {
                    imagesetpixel($image, (int) (($point->getX() / $resizeFactor) + 300 / $resizeFactor), (int) (($point->getY() / $resizeFactor) + 50 + $sideIndex * 250 / $resizeFactor), imagecolorallocate($image, 50, 80, 255));
                }

                try {
                    /** @var BigWidthClassifier $bigWidthClassifier */
                    $bigWidthClassifier = $side->getClassifier(BigWidthClassifier::class);
                    $centerPoint = $bigWidthClassifier->getCenterPoint();
                    imagesetpixel($image, (int) (($centerPoint->getX() / $resizeFactor) + 300 / $resizeFactor), (int) (($centerPoint->getY() / $resizeFactor) + 50 + $sideIndex * 250 / $resizeFactor), imagecolorallocate($image, 0, 150, 150));
                } catch (SideClassifierException) {
                }

                try {
                    /** @var SmallWidthClassifier $smallWidthClassifier */
                    $smallWidthClassifier = $side->getClassifier(SmallWidthClassifier::class);
                    $centerPoint = $smallWidthClassifier->getCenterPoint();
                    imagesetpixel($image, (int) (($centerPoint->getX() / $resizeFactor) + 300 / $resizeFactor), (int) (($centerPoint->getY() / $resizeFactor) + 50 + $sideIndex * 250 / $resizeFactor), imagecolorallocate($image, 150, 150, 0));
                } catch (SideClassifierException) {
                }

                $classifiers = $side->getClassifiers();
                ksort($classifiers);

                $yOffset = 0;
                foreach ($classifiers as $classifier) {
                    imagestring($image, 1, (int) (600 / $resizeFactor), (int) ($yOffset + $sideIndex * 250 / $resizeFactor), $classifier, $black);
                    $yOffset += 10;
                }
            }

            $piece->reduceData();

            file_put_contents(__DIR__ . '/../../resources/Fixtures/Set/' . $content['setName'] . '/piece' . $content['pieceNumber'] . '_piece.ser', serialize($piece));
            file_put_contents(__DIR__ . '/../../resources/Fixtures/Set/' . $content['setName'] . '/piece' . $content['pieceNumber'] . '_piece.json', json_encode($piece));
        } catch (BorderParsingException $exception) {
            echo 'Piece ' . $content['pieceNumber'] . ' failed at BorderFinding: ' . $exception->getMessage() . PHP_EOL;
        } catch (SideParsingException $exception) {
            echo 'Piece ' . $content['pieceNumber'] . ' failed at SideFinding: ' . $exception->getMessage() . PHP_EOL;
        } finally {
            imagepng($image, __DIR__ . '/../../resources/Fixtures/Set/' . $content['setName'] . '/piece' . $content['pieceNumber'] . '_mask.png');
            imagepng($transparentImage, __DIR__ . '/../../resources/Fixtures/Set/' . $content['setName'] . '/piece' . $content['pieceNumber'] . '_transparent.png');
            imagepng($resizedImage, __DIR__ . '/../../resources/Fixtures/Set/' . $content['setName'] . '/piece' . $content['pieceNumber'] . '_transparent_small.png');
        }

        $message->ack();
    }
}

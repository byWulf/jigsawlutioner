<?php

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\Dto\DerivativePoint;
use Bywulf\Jigsawlutioner\Exception\BorderParsingException;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\Exception\SideParsingException;
use Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder;
use Bywulf\Jigsawlutioner\Service\PieceAnalyzer;
use Bywulf\Jigsawlutioner\Service\SideFinder\ByWulfSideFinder;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:pieces:analyze')]
class AnalyzePiecesCommand extends Command
{
    protected function configure()
    {
        $this->addArgument('pieceNumber', InputArgument::IS_ARRAY, 'Piece number if you only want to analyze one piece. If omitted all pieces will be analyzed.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $borderFinder = new ByWulfBorderFinder();
        $sideFinder = new ByWulfSideFinder();
        $pieceAnalyzer = new PieceAnalyzer($borderFinder, $sideFinder);

        $progress = new ProgressBar($output);
        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');

        if (count($input->getArgument('pieceNumber')) > 0) {
            $progress->start(count($input->getArgument('pieceNumber')));

            foreach ($input->getArgument('pieceNumber') as $pieceNumber) {
                $this->analyzePiece((int) $pieceNumber, $pieceAnalyzer);

                $progress->advance();
            }
        } else {
            $progress->start(500);

            for ($pieceNumber = 2; $pieceNumber <= 501; ++$pieceNumber) {
                $this->analyzePiece($pieceNumber, $pieceAnalyzer);

                $progress->advance();
            }
        }

        $progress->finish();

        $output->writeln(PHP_EOL . PHP_EOL . 'Done.');

        return self::SUCCESS;
    }

    private function analyzePiece(int $pieceNumber, PieceAnalyzer $pieceAnalyzer): void
    {
        $image = imagecreatefromjpeg(__DIR__ . '/../../resources/Fixtures/Piece/piece' . $pieceNumber . '.jpg');
        $transparentImage = imagecreatefromjpeg(__DIR__ . '/../../resources/Fixtures/Piece/piece' . $pieceNumber . '.jpg');

        try {
            $piece = $pieceAnalyzer->getPieceFromImage($image, $transparentImage);

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
            foreach ($piece->getSides() as $sideIndex => $side) {
                foreach ($side->getPoints() as $point) {
                    imagesetpixel($image, (int) ($point->getX() / 3) + 100, (int) ($point->getY() / 3) + 50 + $sideIndex * 100, imagecolorallocate($image, 50, 80, 255));
                }

                try {
                    /** @var BigWidthClassifier $bigWidthClassifier */
                    $bigWidthClassifier = $side->getClassifier(BigWidthClassifier::class);
                    $centerPoint = $bigWidthClassifier->getCenterPoint();
                    imagesetpixel($image, (int) ($centerPoint->getX() / 3) + 100, (int) ($centerPoint->getY() / 3) + 50 + $sideIndex * 100, imagecolorallocate($image, 0, 150, 150));
                    imagestring($image, 1, 175, 50 + $sideIndex * 100, 'CX' . round($bigWidthClassifier->getCenterPoint()->getX(), 2) . ' CY' . round($bigWidthClassifier->getCenterPoint()->getY(), 2) . ' W' . round($bigWidthClassifier->getWidth(), 2), imagecolorallocate($image, 0, 150, 150));
                } catch (SideClassifierException) {
                }

                try {
                    /** @var SmallWidthClassifier $smallWidthClassifier */
                    $smallWidthClassifier = $side->getClassifier(SmallWidthClassifier::class);
                    $centerPoint = $smallWidthClassifier->getCenterPoint();
                    imagesetpixel($image, (int) ($centerPoint->getX() / 3) + 100, (int) ($centerPoint->getY() / 3) + 50 + $sideIndex * 100, imagecolorallocate($image, 150, 150, 0));
                    imagestring($image, 1, 175, 60 + $sideIndex * 100, 'CX' . round($smallWidthClassifier->getCenterPoint()->getX(), 2) . ' CY' . round($smallWidthClassifier->getCenterPoint()->getY(), 2) . ' W' . round($smallWidthClassifier->getWidth(), 2), imagecolorallocate($image, 150, 150, 0));
                } catch (SideClassifierException) {
                }

                imagestring($image, 5, 175, 30 + $sideIndex * 100, $side->getClassifier(DirectionClassifier::class)->getDirection(), $black);
            }

            file_put_contents(__DIR__ . '/../../resources/Fixtures/Piece/piece' . $pieceNumber . '_piece.ser', serialize($piece));
            file_put_contents(__DIR__ . '/../../resources/Fixtures/Piece/piece' . $pieceNumber . '_piece.json', json_encode($piece));
        } catch (BorderParsingException $exception) {
            echo 'Piece ' . $pieceNumber . ' failed at BorderFinding: ' . $exception->getMessage() . PHP_EOL;
        } catch (SideParsingException $exception) {
            echo 'Piece ' . $pieceNumber . ' failed at SideFinding: ' . $exception->getMessage() . PHP_EOL;
        } finally {
            imagepng($image, __DIR__ . '/../../resources/Fixtures/Piece/piece' . $pieceNumber . '_mask.png');
            imagepng($transparentImage, __DIR__ . '/../../resources/Fixtures/Piece/piece' . $pieceNumber . '_transparent.png');
        }
    }
}

<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Solution;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class SolutionOutputter
{
    private PointService $pointService;
    private Filesystem $filesystem;

    public function __construct()
    {
        $this->pointService = new PointService();
        $this->filesystem = new Filesystem();
    }

    public function outputAsHtml(Solution $solution, string $placementHtmlPath, string $transparentImagePathPattern, float $resizeFactor = 0.1): void
    {
        $width = 0;
        foreach ($solution->getGroups() as $group) {
            $width = max($width, $group->getWidth());
        }

        $groups = [];

        foreach ($solution->getGroups() as $group) {
            $widths = [];
            $heights = [];
            $lefts = [];
            $tops = [];
            foreach ($group->getPlacements() as $placement) {
                $widths[$placement->getX()][] = $placement->getWidth();
                $heights[$placement->getY()][] = $placement->getHeight();
            }
            foreach ($widths as $x => $widthArray) {
                $widths[$x] = array_sum($widthArray) / count($widthArray);
            }
            foreach ($heights as $y => $heightsArray) {
                $heights[$y] = array_sum($heightsArray) / count($heightsArray);
            }

            $currentLeft = 0;
            for ($x = min(array_keys($widths)); $x <= max(array_keys($widths)); $x++) {
                $currentLeft += $widths[$x] / 2;
                $lefts[$x] = $currentLeft;
                $currentLeft += $widths[$x] / 2;
            }

            $currentTop = 0;
            for ($y = min(array_keys($heights)); $y <= max(array_keys($heights)); $y++) {
                $currentTop += $heights[$y] / 2;
                $tops[$y] = $currentTop;
                $currentTop += $heights[$y] / 2;
            }

            $pieces = [];
            $pieceIndexes = [];
            foreach ($group->getPlacements() as $placement) {
                $piece = $placement->getPiece();

                $topSide = $piece->getSide($placement->getTopSideIndex());
                $bottomSide = $piece->getSide($placement->getTopSideIndex() + 2);

                $rotation = -$this->pointService->getAverageRotation($topSide->getEndPoint(), $bottomSide->getStartPoint(), $bottomSide->getEndPoint(), $topSide->getStartPoint());

                //$rotation = -$this->pointService->getRotation($topSide->getStartPoint(), $topSide->getEndPoint()) + 180;

                $left = $lefts[$placement->getX()];
                $top = $tops[$placement->getY()];
                $center = $this->pointService->getAveragePoint([
                    $piece->getSide(0)->getStartPoint(),
                    $piece->getSide(1)->getStartPoint(),
                    $piece->getSide(2)->getStartPoint(),
                    $piece->getSide(3)->getStartPoint(),
                ]);

                $readableContext = [];

                if ($placement->getContext() !== null) {
                    foreach ($placement->getContext() as $sideIndex => $sideContext) {
                        $readableContext[$sideIndex] = '#' . ($sideContext['matchedProbabilityIndex'] !== null ? $sideContext['matchedProbabilityIndex'] + 1 : '---') . ' (' . implode(', ', array_map(fn (float $num): float => round($num, 2), array_slice($sideContext['probabilities'], 0, 10))) . ($sideContext['matchedProbabilityIndex'] > 9 ? ', ..., ' . round($sideContext['probabilities'][$sideContext['matchedProbabilityIndex']], 2) : '') . ')';
                    }
                }

                $pieces[] = [
                    'src' => Path::makeRelative(
                        sprintf($transparentImagePathPattern, $piece->getIndex()),
                        dirname($placementHtmlPath)
                    ),
                    'style' => [
                        'left' => ($left * $resizeFactor) . 'px',
                        'top' => ($top * $resizeFactor) . 'px',
                        'width' => ($piece->getImageWidth() * $resizeFactor) . 'px',
                        'height' => ($piece->getImageHeight() * $resizeFactor) . 'px',
                        'margin-left' => -($piece->getImageWidth() / 2 * $resizeFactor) . 'px',
                        'margin-top' => -($piece->getImageHeight() / 2 * $resizeFactor) . 'px',
                        'transform' => 'rotate(' . $rotation . 'deg) translateX(' . ((($piece->getImageWidth() / 2) - $center->getX()) * $resizeFactor) . 'px) translateY(' . ((($piece->getImageHeight() / 2) - $center->getY()) * $resizeFactor) . 'px)',
                    ],
                    'overlayStyle' => [
                        'left' => (($left - $placement->getWidth() / 2) * $resizeFactor) . 'px',
                        'top' => (($top - $placement->getHeight() / 2) * $resizeFactor) . 'px',
                        'width' => ($placement->getWidth() * $resizeFactor) . 'px',
                        'height' => ($placement->getHeight() * $resizeFactor) . 'px',
                    ],
                    'title' => sprintf(
                        'Piece #%s, TopSide: %s, X: %s, Y: %s',
                        $placement->getPiece()->getIndex(),
                        $placement->getTopSideIndex(),
                        $placement->getX(),
                        $placement->getY()
                    ),
                    'readableContext' => $readableContext,
                ];
                $pieceIndexes[] = $placement->getPiece()->getIndex();
            }
            $groups[] = [
                'containerStyle' => [
                    'width' => (array_sum($widths) * $resizeFactor) . 'px',
                    'height' => (array_sum($heights) * $resizeFactor) . 'px',
                ],
                'solutionStyle' => [
                ],
                'pieces' => $pieces,
                'pieceIndexes' => $pieceIndexes,
            ];
        }

        $this->writeHtml($groups, $placementHtmlPath, $resizeFactor);
    }

    private function writeHtml(array $groups, string $placementHtmlPath, float $resizeFactor): void
    {
        $twig = new Environment(new ArrayLoader([
            'solution' => <<<HTML
                <html>
                    <head>
                        <style>
                            .solution-container {
                                background-color: #cde;
                                box-shadow: 0 0 {{ 100 * resizeFactor }}px #cde, 0 0 {{ 100 * resizeFactor }}px #cde, 0 0 {{ 100 * resizeFactor }}px #cde;
                                margin: {{ 300 * resizeFactor }}px;
                                float: left;
                            }
                            
                            .solution {
                                position: relative;
                            }
                            
                            .solution .piece {
                                position: absolute;
                                z-index: 1;
                            }
                            
                            .solution .piece-overlay {
                                position: absolute;
                                z-index: 5;
                            }
                            .solution .piece-overlay:hover {
                                background-color: rgba(255, 255, 255, 0.2);
                            }
                            .solution .piece-overlay-left {
                                position: absolute;
                                width: 30%;
                                height: 80%;
                                left: 0;
                                top: 10%;
                            }
                            .solution .piece-overlay-right {
                                position: absolute;
                                width: 30%;
                                height: 80%;
                                right: 0;
                                top: 10%;
                            }
                            .solution .piece-overlay-top {
                                position: absolute;
                                width: 80%;
                                height: 30%;
                                left: 10%;
                                top: 0;
                            }
                            .solution .piece-overlay-bottom {
                                position: absolute;
                                width: 80%;
                                height: 30%;
                                left: 10%;
                                bottom: 0;
                            }
                            .solution .piece-overlay-side:hover {
                                background-color: rgba(255, 200, 150, 0.3);
                            }
                        </style>
                    </head>
                    <body>
                        {% for group in groups %}
                            <div class="solution-container" style="{% for name, value in group.containerStyle %}{{ name }}: {{ value }};{% endfor %}">
                                <div class="solution" style="{% for name, value in group.solutionStyle %}{{ name }}: {{ value }};{% endfor %}" data-piece-indexes="{{ group.pieceIndexes|join(',') }}">
                                    {% for piece in group.pieces %}
                                        <img class="piece" src="{{ piece.src }}" style="{% for name, value in piece.style %}{{ name }}: {{ value }};{% endfor %}">
                                        <div class="piece-overlay" style="{% for name, value in piece.overlayStyle %}{{ name }}: {{ value }};{% endfor %}" title="{{ piece.title }}">
                                            <div class="piece-overlay-side piece-overlay-top" title="{{ piece.readableContext[0] }}"></div>
                                            <div class="piece-overlay-side piece-overlay-left" title="{{ piece.readableContext[1] }}"></div>
                                            <div class="piece-overlay-side piece-overlay-bottom" title="{{ piece.readableContext[2] }}"></div>
                                            <div class="piece-overlay-side piece-overlay-right" title="{{ piece.readableContext[3] }}"></div>
                                        </div>
                                    {% endfor %}
                                </div>
                            </div>
                        {% endfor %}
                    </body>
                </html>
            HTML
        ]));

        $this->filesystem->dumpFile($placementHtmlPath, $twig->render('solution', ['groups' => $groups, 'resizeFactor' => $resizeFactor]));
    }

    public function outputAsText(Solution $solution): void
    {
        foreach ($solution->getGroups() as $index => $group) {
            echo 'Group #' . $index . ':' . PHP_EOL;
            foreach ($group->getPlacements() as $placement) {
                echo "\t" . 'x: ' . $placement->getX() . ', y: ' . $placement->getY() . ', top side: ' . $placement->getTopSideIndex() . ', pieceIndex: ' . $placement->getPiece()->getIndex() . PHP_EOL;
            }
            echo PHP_EOL;
        }
    }
}

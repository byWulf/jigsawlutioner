<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service;

use Bywulf\Jigsawlutioner\Dto\DerivativePoint;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Exception\BorderParsingException;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\Exception\SideParsingException;
use Bywulf\Jigsawlutioner\Service\BorderFinder\BorderFinderInterface;
use Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder;
use Bywulf\Jigsawlutioner\Service\PathService;
use Bywulf\Jigsawlutioner\Service\PieceAnalyzer;
use Bywulf\Jigsawlutioner\Service\SideFinder\ByWulfSideFinder;
use Bywulf\Jigsawlutioner\Service\SideFinder\SideFinderInterface;
use Bywulf\Jigsawlutioner\Service\SideMatcher\WeightedMatcher;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\CornerDistanceClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DepthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use ReflectionClass;
use Rubix\ML\Classifiers\ClassificationTree;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;

class PieceAnalyzerTest extends TestCase
{
    use ProphecyTrait;

    public function testGetPieceFromImage(): void
    {
        /** @var BorderFinderInterface|ObjectProphecy $borderFinder */
        $borderFinder = $this->prophesize(BorderFinderInterface::class);
        /** @var SideFinderInterface|ObjectProphecy $sideFinder */
        $sideFinder = $this->prophesize(SideFinderInterface::class);
        /** @var PathService|ObjectProphecy $pathService */
        $pathService = $this->prophesize(PathService::class);

        $image = imagecreate(100, 100);

        $borderPoints = [new Point(0, 1), new Point(1, 2), new Point(2, 3)];
        $borderFinder->findPieceBorder($image)->shouldBeCalledOnce()->willReturn($borderPoints);

        $sideFinder->getSides($borderPoints)->shouldBeCalledOnce()->willReturn([
            new Side([new Point(0, 0)], new Point(0, 0), new Point(0, 0)),
            new Side([new Point(1, 1)], new Point(1, 1), new Point(1, 1)),
            new Side([new Point(2, 2)], new Point(2, 2), new Point(2, 2)),
            new Side([new Point(3, 3)], new Point(3, 3), new Point(3, 3)),
        ]);

        $pathService->softenPolyline([new Point(0, 0)], 10, 100)->shouldBeCalled()->willReturn([new Point(0.5, 0.5)]);
        $pathService->softenPolyline([new Point(1, 1)], 10, 100)->shouldBeCalled()->willReturn([new Point(1.5, 1.5)]);
        $pathService->softenPolyline([new Point(2, 2)], 10, 100)->shouldBeCalled()->willReturn([new Point(2.5, 2.5)]);
        $pathService->softenPolyline([new Point(3, 3)], 10, 100)->shouldBeCalled()->willReturn([new Point(3.5, 3.5)]);

        $pathService->rotatePointsToCenter([new Point(0.5, 0.5)])->shouldBeCalled()->willReturn([new Point(-0.5, -0.5)]);
        $pathService->rotatePointsToCenter([new Point(1.5, 1.5)])->shouldBeCalled()->willReturn([new Point(-1.5, -1.5)]);
        $pathService->rotatePointsToCenter([new Point(2.5, 2.5)])->shouldBeCalled()->willReturn([new Point(-2.5, -2.5)]);
        $pathService->rotatePointsToCenter([new Point(3.5, 3.5)])->shouldBeCalled()->willReturn([new Point(-3.5, -3.5)]);

        $pieceAnalyzer = new PieceAnalyzer($borderFinder->reveal(), $sideFinder->reveal());

        $reflectionClass = new ReflectionClass(PieceAnalyzer::class);
        $reflectionProperty = $reflectionClass->getProperty('pathService');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($pieceAnalyzer, $pathService->reveal());

        $expectedPiece = new Piece(
            $borderPoints,
            [
                (new Side([new Point(-0.5, -0.5)], new Point(0, 0), new Point(0, 0)))
                    ->addClassifier(new DirectionClassifier(DirectionClassifier::NOP_INSIDE))
                    ->addClassifier(new CornerDistanceClassifier(0))
                    ->addClassifier(new DepthClassifier(DirectionClassifier::NOP_INSIDE, -0.5)),
                (new Side([new Point(-1.5, -1.5)], new Point(1, 1), new Point(1, 1)))
                    ->addClassifier(new DirectionClassifier(DirectionClassifier::NOP_INSIDE))
                    ->addClassifier(new CornerDistanceClassifier(0))
                    ->addClassifier(new DepthClassifier(DirectionClassifier::NOP_INSIDE, -1.5)),
                (new Side([new Point(-2.5, -2.5)], new Point(2, 2), new Point(2, 2)))
                    ->addClassifier(new DirectionClassifier(DirectionClassifier::NOP_INSIDE))
                    ->addClassifier(new CornerDistanceClassifier(0))
                    ->addClassifier(new DepthClassifier(DirectionClassifier::NOP_INSIDE, -2.5)),
                (new Side([new Point(-3.5, -3.5)], new Point(3, 3), new Point(3, 3)))
                    ->addClassifier(new DirectionClassifier(DirectionClassifier::NOP_INSIDE))
                    ->addClassifier(new CornerDistanceClassifier(0))
                    ->addClassifier(new DepthClassifier(DirectionClassifier::NOP_INSIDE, -3.5)),
            ]
        );

        $this->assertEquals($expectedPiece, $pieceAnalyzer->getPieceFromImage($image));
    }

    public function testCornersFromFixtures(): void
    {
        $borderFinder = new ByWulfBorderFinder();
        $sideFinder = new ByWulfSideFinder();
        $pieceAnalyzer = new PieceAnalyzer($borderFinder, $sideFinder);

        $corners = [
            '2' => [new Point(450, 300), new Point(169, 540), new Point(406, 810), new Point(674, 568)],
            '3' => [new Point(454, 244), new Point(256, 488), new Point(526, 722), new Point(739, 468)],
            '4' => [new Point(187, 368), new Point(429, 641), new Point(663, 439), new Point(416, 173)],
            '5' => [new Point(417, 198), new Point(195, 445), new Point(476, 682), new Point(678, 434)],
            '6' => [new Point(380, 183), new Point(202, 435), new Point(491, 644), new Point(667, 360)],
            '7' => [new Point(184, 466), new Point(430, 693), new Point(691, 451), new Point(449, 210)],
            '8' => [new Point(227, 397), new Point(451, 654), new Point(695, 450), new Point(470, 191)],
            '9' => [new Point(247, 397), new Point(496, 639), new Point(724, 418), new Point(499, 145)],
            '10' => [new Point(335, 218), new Point(162, 438), new Point(422, 688), new Point(650, 422)],
            '11' => [new Point(490, 192), new Point(185, 386), new Point(419, 686), new Point(686, 493)],
            '12' => [new Point(187, 458), new Point(464, 704), new Point(668, 445), new Point(424, 194)],
            '13' => [new Point(411, 174), new Point(202, 368), new Point(422, 642), new Point(672, 420)],
            '14' => [new Point(204, 439), new Point(479, 669), new Point(683, 433), new Point(438, 188)],
            '15' => [new Point(187, 443), new Point(432, 699), new Point(663, 450), new Point(426, 200)],
            '16' => [new Point(382, 165), new Point(197, 460), new Point(489, 658), new Point(668, 338)],
            '17' => [new Point(433, 194), new Point(208, 380), new Point(428, 637), new Point(657, 427)],
            '18' => [new Point(208, 499), new Point(460, 711), new Point(698, 451), new Point(474, 226)],
            '19' => [new Point(455, 288), new Point(270, 497), new Point(507, 717), new Point(725, 493)],
            '20' => [new Point(497, 283), new Point(255, 528), new Point(511, 746), new Point(760, 527)],
            '21' => [new Point(175, 484), new Point(413, 755), new Point(654, 526), new Point(438, 271)],
            '22' => [new Point(453, 246), new Point(207, 418), new Point(404, 691), new Point(674, 494)],
            '23' => [new Point(176, 459), new Point(417, 691), new Point(684, 469), new Point(468, 219)],
            '24' => [new Point(215, 445), new Point(406, 718), new Point(660, 530), new Point(465, 278)],
            '25' => [new Point(367, 238), new Point(193, 551), new Point(483, 687), new Point(640, 383)],
            '26' => [new Point(239, 461), new Point(453, 691), new Point(711, 460), new Point(495, 231)],
            '27' => [new Point(477, 228), new Point(216, 463), new Point(419, 688), new Point(675, 444)],
            '28' => [new Point(424, 180), new Point(253, 460), new Point(507, 610), new Point(675, 335)],
            '29' => [new Point(444, 237), new Point(286, 499), new Point(542, 663), new Point(713, 376)],
            '30' => [new Point(215, 385), new Point(318, 667), new Point(627, 577), new Point(516, 279)],
            '31' => [new Point(462, 219), new Point(255, 477), new Point(506, 677), new Point(685, 402)],
            '32' => [new Point(197, 421), new Point(347, 673), new Point(672, 509), new Point(513, 265)],
            '33' => [new Point(458, 235), new Point(301, 508), new Point(566, 637), new Point(723, 366)],
            '34' => [new Point(239, 388), new Point(349, 658), new Point(634, 577), new Point(541, 291)],
            '35' => [],
            '36' => [new Point(189, 360), new Point(332, 620), new Point(610, 452), new Point(473, 199)],
            '37' => [new Point(412, 235), new Point(284, 534), new Point(551, 657), new Point(657, 356)],
            '38' => [new Point(369, 289), new Point(206, 581), new Point(449, 720), new Point(631, 436)],
            '39' => [new Point(294, 339), new Point(375, 624), new Point(685, 523), new Point(587, 245)],
            '40' => [new Point(392, 156), new Point(229, 448), new Point(483, 579), new Point(667, 291)], // TODO: Not yet the correct points
            '41' => [new Point(210, 407), new Point(359, 675), new Point(677, 518), new Point(539, 246)],
            '42' => [new Point(402, 181), new Point(235, 441), new Point(489, 613), new Point(665, 362)],
            '43' => [new Point(529, 187), new Point(274, 428), new Point(491, 666), new Point(729, 404)],
            '44' => [new Point(380, 352), new Point(229, 624), new Point(499, 761), new Point(625, 486)],
            '45' => [new Point(162, 401), new Point(283, 658), new Point(573, 499), new Point(461, 272)],
            '46' => [new Point(437, 203), new Point(289, 491), new Point(502, 632), new Point(683, 343)],
            '47' => [new Point(396, 251), new Point(208, 528), new Point(441, 688), new Point(673, 380)],
            '48' => [new Point(244, 477), new Point(443, 716), new Point(673, 526), new Point(541, 307)],
            '49' => [new Point(363, 288), new Point(242, 575), new Point(478, 685), new Point(651, 394)],
            '50' => [new Point(236, 442), new Point(315, 739), new Point(632, 675), new Point(568, 372)],
            '51' => [new Point(355, 278), new Point(201, 584), new Point(489, 721), new Point(627, 407)],
            '52' => [new Point(166, 427), new Point(318, 725), new Point(621, 553), new Point(480, 266)],
            '53' => [new Point(246, 330), new Point(298, 639), new Point(607, 557), new Point(563, 282)],
            '54' => [new Point(400, 169), new Point(200, 438), new Point(415, 604), new Point(634, 321)],
            '55' => [new Point(381, 139), new Point(211, 409), new Point(454, 548), new Point(649, 298)],
            '56' => [new Point(163, 419), new Point(271, 715), new Point(587, 601), new Point(450, 270)],
            '57' => [new Point(219, 491), new Point(516, 705), new Point(700, 455), new Point(451, 216)],
            '58' => [new Point(404, 180), new Point(223, 433), new Point(483, 659), new Point(677, 373)],
            '59' => [new Point(215, 442), new Point(430, 708), new Point(688, 487), new Point(450, 266)],
            '60' => [new Point(201, 479), new Point(496, 641), new Point(655, 382), new Point(440, 203)],
            '61' => [new Point(400, 231), new Point(270, 529), new Point(507, 675), new Point(663, 372)],
            '62' => [new Point(254, 535), new Point(480, 736), new Point(698, 483), new Point(439, 276)],
            '63' => [new Point(205, 425), new Point(415, 672), new Point(682, 462), new Point(480, 236)],
            '64' => [new Point(376, 153), new Point(161, 390), new Point(396, 594), new Point(627, 344)],
            '65' => [new Point(257, 548), new Point(530, 705), new Point(729, 462), new Point(469, 273)],
            '66' => [new Point(236, 570), new Point(459, 814), new Point(699, 540), new Point(488, 325)],
            '67' => [new Point(238, 465), new Point(415, 716), new Point(665, 544), new Point(492, 300)],
            '68' => [new Point(184, 534), new Point(451, 668), new Point(610, 368), new Point(324, 213)],
            '69' => [new Point(388, 205), new Point(234, 465), new Point(512, 640), new Point(677, 400)],
            '70' => [new Point(461, 275), new Point(265, 538), new Point(528, 760), new Point(753, 427)],
            '71' => [new Point(194, 408), new Point(310, 714), new Point(604, 642), new Point(531, 352)],
            '72' => [new Point(201, 527), new Point(398, 758), new Point(698, 534), new Point(512, 303)],
            '73' => [new Point(308, 206), new Point(208, 479), new Point(474, 627), new Point(604, 368)],
            '74' => [new Point(507, 246), new Point(223, 422), new Point(432, 693), new Point(685, 480)],
            '75' => [new Point(380, 270), new Point(280, 574), new Point(569, 674), new Point(681, 361)],
            '76' => [new Point(231, 512), new Point(415, 763), new Point(704, 581), new Point(506, 306)],
            '77' => [new Point(155, 390), new Point(253, 666), new Point(571, 517), new Point(477, 260)],
            '78' => [new Point(338, 219), new Point(256, 524), new Point(522, 621), new Point(640, 333)],
            '79' => [new Point(480, 246), new Point(149, 388), new Point(285, 681), new Point(623, 549)],
            '80' => [new Point(226, 496), new Point(519, 662), new Point(661, 366), new Point(414, 238)],
            '81' => [new Point(329, 246), new Point(237, 564), new Point(520, 637), new Point(599, 347)],
            '82' => [new Point(216, 434), new Point(346, 692), new Point(623, 512), new Point(479, 275)],
            '83' => [new Point(388, 267), new Point(293, 594), new Point(572, 680), new Point(669, 345)],
            '84' => [new Point(350, 252), new Point(210, 561), new Point(479, 682), new Point(589, 364)],
            '85' => [new Point(417, 217), new Point(285, 491), new Point(519, 637), new Point(691, 368)],
            '86' => [new Point(403, 240), new Point(262, 547), new Point(552, 663), new Point(700, 368)],
            '87' => [new Point(470, 278), new Point(246, 524), new Point(495, 739), new Point(715, 483)],
            '88' => [new Point(326, 238), new Point(213, 558), new Point(518, 654), new Point(614, 328)],
            '89' => [new Point(413, 307), new Point(163, 541), new Point(379, 768), new Point(628, 534)],
            '90' => [new Point(214, 590), new Point(525, 648), new Point(569, 334), new Point(291, 289)],
            '91' => [new Point(382, 292), new Point(209, 606), new Point(446, 762), new Point(620, 442)],
            '92' => [new Point(394, 244), new Point(306, 531), new Point(585, 610), new Point(681, 336)],
            '93' => [new Point(401, 291), new Point(154, 527), new Point(371, 742), new Point(607, 507)],
            '94' => [new Point(486, 306), new Point(281, 512), new Point(497, 717), new Point(693, 485)],
            '95' => [new Point(422, 198), new Point(314, 581), new Point(574, 672), new Point(674, 289)],
            '96' => [new Point(334, 253), new Point(250, 537), new Point(511, 630), new Point(620, 368)],
            '97' => [new Point(217, 556), new Point(414, 801), new Point(680, 566), new Point(503, 319)],
            '98' => [new Point(207, 504), new Point(404, 736), new Point(617, 492), new Point(396, 285)],
            '99' => [new Point(363, 343), new Point(257, 656), new Point(541, 772), new Point(660, 450)],
            '100' => [new Point(223, 555), new Point(465, 754), new Point(668, 505), new Point(448, 310)],
            '101' => [new Point(486, 301), new Point(245, 537), new Point(455, 748), new Point(701, 498)],
            '102' => [new Point(485, 286), new Point(216, 504), new Point(370, 731), new Point(654, 540)],
            '103' => [new Point(421, 392), new Point(189, 604), new Point(408, 822), new Point(663, 573)],
            '104' => [new Point(221, 394), new Point(387, 649), new Point(682, 500), new Point(552, 260)],
            '105' => [new Point(435, 281), new Point(262, 560), new Point(495, 718), new Point(663, 451)],
            '106' => [new Point(246, 492), new Point(411, 729), new Point(674, 539), new Point(483, 310)],
            '107' => [new Point(194, 471), new Point(355, 729), new Point(641, 555), new Point(472, 298)],
            '108' => [new Point(187, 541), new Point(387, 764), new Point(654, 543), new Point(459, 324)],
            '109' => [new Point(395, 252), new Point(226, 539), new Point(474, 705), new Point(656, 432)],
            '110' => [new Point(221, 502), new Point(405, 756), new Point(679, 549), new Point(486, 322)],
            '111' => [],
            '112' => [new Point(279, 439), new Point(532, 546), new Point(660, 224), new Point(413, 131)],
            '113' => [new Point(386, 230), new Point(212, 518), new Point(450, 643), new Point(610, 371)],
            '114' => [new Point(483, 305), new Point(241, 540), new Point(423, 731), new Point(670, 483)],
            '115' => [new Point(373, 311), new Point(245, 599), new Point(483, 713), new Point(605, 423)],
            '116' => [new Point(469, 249), new Point(238, 525), new Point(430, 716), new Point(676, 459)],
            '117' => [new Point(258, 503), new Point(429, 746), new Point(687, 581), new Point(500, 334)],
            '118' => [new Point(242, 442), new Point(416, 698), new Point(695, 491), new Point(510, 253)],
            '119' => [new Point(332, 264), new Point(231, 549), new Point(521, 651), new Point(618, 355)],
            '120' => [new Point(474, 251), new Point(187, 518), new Point(394, 748), new Point(666, 532)],
            '121' => [new Point(421, 308), new Point(291, 559), new Point(595, 726), new Point(730, 427)],
            '122' => [new Point(242, 570), new Point(551, 713), new Point(692, 400), new Point(408, 264)],
            '123' => [new Point(281, 580), new Point(573, 692), new Point(681, 384), new Point(398, 279)],
            '124' => [new Point(448, 295), new Point(245, 566), new Point(492, 743), new Point(665, 497)],
            '125' => [new Point(219, 490), new Point(414, 716), new Point(659, 436), new Point(432, 253)],
            '126' => [new Point(256, 639), new Point(534, 769), new Point(684, 470), new Point(426, 334)],
            '127' => [new Point(470, 211), new Point(220, 447), new Point(413, 643), new Point(666, 416)],
            '128' => [new Point(317, 176), new Point(190, 500), new Point(472, 595), new Point(598, 267)],
            '129' => [new Point(308, 233), new Point(197, 544), new Point(487, 636), new Point(594, 312)],
            '130' => [new Point(235, 532), new Point(480, 717), new Point(670, 491), new Point(429, 286)],
            '131' => [new Point(388, 211), new Point(196, 466), new Point(455, 647), new Point(637, 375)],
            '132' => [new Point(405, 169), new Point(265, 469), new Point(543, 596), new Point(673, 295)],
            '133' => [new Point(417, 243), new Point(181, 490), new Point(402, 699), new Point(627, 441)],
            '134' => [new Point(430, 315), new Point(209, 554), new Point(418, 763), new Point(653, 518)],
            '135' => [new Point(190, 479), new Point(411, 688), new Point(659, 430), new Point(427, 239)],
            '136' => [new Point(150, 484), new Point(384, 669), new Point(601, 475), new Point(353, 245)],
            '137' => [new Point(455, 233), new Point(263, 522), new Point(538, 720), new Point(704, 392)],
            '138' => [new Point(206, 493), new Point(415, 712), new Point(650, 519), new Point(445, 286)],
            '139' => [new Point(419, 171), new Point(257, 474), new Point(540, 626), new Point(703, 330)],
            '140' => [new Point(204, 460), new Point(411, 708), new Point(662, 484), new Point(425, 244)],
            '141' => [new Point(353, 232), new Point(240, 564), new Point(563, 656), new Point(656, 350)],
            '142' => [new Point(382, 259), new Point(240, 525), new Point(531, 675), new Point(638, 362)],
            '143' => [new Point(355, 280), new Point(266, 612), new Point(540, 685), new Point(624, 360)],
            '144' => [new Point(470, 333), new Point(245, 548), new Point(425, 762), new Point(683, 550)],
            '145' => [new Point(397, 259), new Point(243, 568), new Point(524, 684), new Point(662, 383)],
            '146' => [new Point(268, 481), new Point(465, 694), new Point(722, 473), new Point(513, 265)],
            '147' => [new Point(495, 231), new Point(232, 444), new Point(415, 669), new Point(669, 470)],
            '148' => [new Point(375, 289), new Point(282, 599), new Point(564, 697), new Point(674, 405)],
            '149' => [new Point(345, 283), new Point(148, 506), new Point(380, 743), new Point(626, 469)],
            '150' => [new Point(174, 533), new Point(443, 724), new Point(657, 465), new Point(427, 266)],
            '151' => [new Point(221, 502), new Point(433, 724), new Point(665, 497), new Point(461, 279)],
            '152' => [new Point(508, 215), new Point(225, 403), new Point(424, 676), new Point(686, 456)],
            '153' => [new Point(262, 480), new Point(434, 721), new Point(725, 513), new Point(559, 291)],
            '154' => [new Point(373, 211), new Point(235, 523), new Point(496, 627), new Point(636, 353)],
            '155' => [new Point(231, 399), new Point(358, 672), new Point(653, 511), new Point(489, 251)],
            '156' => [new Point(355, 277), new Point(230, 578), new Point(520, 695), new Point(676, 415)],
            '157' => [new Point(260, 486), new Point(460, 773), new Point(711, 546), new Point(518, 288)],
            '158' => [new Point(385, 296), new Point(271, 618), new Point(575, 738), new Point(685, 405)],
            '159' => [new Point(462, 254), new Point(236, 499), new Point(470, 712), new Point(680, 467)],
            '160' => [new Point(242, 451), new Point(410, 705), new Point(700, 500), new Point(526, 240)],
            '161' => [new Point(412, 302), new Point(266, 553), new Point(547, 699), new Point(675, 419)],
            '162' => [new Point(520, 234), new Point(246, 473), new Point(404, 718), new Point(704, 513)],
            '163' => [new Point(366, 228), new Point(218, 491), new Point(512, 639), new Point(637, 350)],
            '164' => [new Point(198, 451), new Point(358, 709), new Point(641, 552), new Point(492, 291)],
            '165' => [new Point(362, 283), new Point(258, 602), new Point(546, 695), new Point(659, 366)],
            '166' => [new Point(436, 281), new Point(247, 531), new Point(500, 714), new Point(693, 460)],
            '167' => [new Point(212, 453), new Point(387, 704), new Point(662, 539), new Point(479, 259)],
            '168' => [new Point(261, 475), new Point(460, 745), new Point(717, 535), new Point(525, 277)],
            '169' => [new Point(357, 308), new Point(261, 624), new Point(577, 711), new Point(661, 371)],
            '170' => [new Point(541, 244), new Point(281, 441), new Point(482, 676), new Point(733, 501)],
            '171' => [new Point(372, 257), new Point(222, 563), new Point(515, 707), new Point(642, 385)],
            '172' => [new Point(271, 561), new Point(545, 701), new Point(668, 407), new Point(409, 273)],
            '173' => [new Point(378, 262), new Point(256, 549), new Point(539, 660), new Point(640, 340)],
            '174' => [new Point(220, 482), new Point(420, 669), new Point(683, 427), new Point(486, 232)],
            '175' => [new Point(353, 248), new Point(214, 554), new Point(470, 684), new Point(645, 385)],
            '176' => [new Point(226, 573), new Point(487, 772), new Point(677, 525), new Point(424, 319)],
            '177' => [new Point(224, 580), new Point(461, 793), new Point(686, 533), new Point(436, 313)],
            '178' => [new Point(416, 260), new Point(284, 592), new Point(600, 720), new Point(730, 375)],
            '179' => [new Point(221, 460), new Point(444, 713), new Point(674, 560), new Point(480, 295)],
            '180' => [new Point(408, 292), new Point(240, 577), new Point(529, 751), new Point(670, 418)],
            '181' => [new Point(441, 305), new Point(253, 561), new Point(510, 704), new Point(666, 440)],
            '182' => [new Point(226, 531), new Point(397, 728), new Point(671, 511), new Point(461, 293)],
            '183' => [new Point(341, 268), new Point(227, 598), new Point(516, 700), new Point(631, 379)],
            '184' => [new Point(384, 294), new Point(229, 575), new Point(501, 727), new Point(636, 451)],
            '185' => [new Point(397, 250), new Point(279, 581), new Point(554, 688), new Point(682, 356)],
            '186' => [new Point(495, 333), new Point(296, 564), new Point(520, 767), new Point(734, 528)],
            '187' => [new Point(469, 329), new Point(213, 582), new Point(422, 804), new Point(645, 555)],
            '188' => [new Point(191, 522), new Point(356, 759), new Point(627, 559), new Point(418, 307)],
            '189' => [new Point(270, 263), new Point(166, 569), new Point(480, 677), new Point(579, 359)],
            '190' => [new Point(202, 618), new Point(455, 832), new Point(635, 548), new Point(435, 361)],
            '191' => [new Point(349, 300), new Point(258, 605), new Point(519, 702), new Point(627, 394)],
            '192' => [new Point(136, 549), new Point(310, 797), new Point(585, 601), new Point(399, 367)],
            '193' => [new Point(332, 237), new Point(239, 552), new Point(522, 648), new Point(624, 317)],
            '194' => [new Point(436, 310), new Point(202, 568), new Point(449, 764), new Point(657, 504)],
            '195' => [new Point(433, 298), new Point(213, 510), new Point(431, 720), new Point(647, 475)],
            '196' => [new Point(476, 328), new Point(243, 583), new Point(456, 770), new Point(674, 530)],
            '197' => [new Point(421, 300), new Point(266, 573), new Point(495, 728), new Point(672, 440)],
            '198' => [new Point(252, 530), new Point(434, 756), new Point(682, 530), new Point(514, 324)],
            '199' => [new Point(358, 252), new Point(238, 586), new Point(498, 655), new Point(635, 347)],
            '200' => [new Point(245, 567), new Point(445, 781), new Point(701, 559), new Point(501, 337)],
            '201' => [new Point(553, 294), new Point(334, 507), new Point(521, 740), new Point(766, 516)],
            '202' => [new Point(451, 254), new Point(174, 448), new Point(358, 707), new Point(629, 503)],
            '203' => [new Point(542, 238), new Point(282, 498), new Point(501, 719), new Point(765, 468)],
            '204' => [new Point(342, 348), new Point(206, 586), new Point(500, 728), new Point(614, 450)],
            '205' => [new Point(378, 239), new Point(212, 561), new Point(468, 698), new Point(628, 391)],
            '206' => [new Point(213, 448), new Point(415, 656), new Point(642, 428), new Point(433, 238)],
            '207' => [new Point(370, 211), new Point(236, 530), new Point(517, 613), new Point(628, 302)],
            '208' => [new Point(274, 515), new Point(450, 721), new Point(701, 498), new Point(522, 285)],
            '209' => [new Point(365, 206), new Point(277, 497), new Point(539, 605), new Point(662, 293)],
            '210' => [new Point(385, 216), new Point(198, 516), new Point(478, 655), new Point(626, 366)],
            '211' => [new Point(211, 438), new Point(374, 674), new Point(642, 484), new Point(480, 268)],
            '212' => [new Point(391, 160), new Point(243, 450), new Point(482, 587), new Point(639, 290)],
            '213' => [new Point(298, 174), new Point(183, 484), new Point(462, 544), new Point(605, 236)],
            '214' => [new Point(153, 477), new Point(382, 692), new Point(612, 458), new Point(396, 249)],
            '215' => [new Point(378, 197), new Point(238, 498), new Point(499, 649), new Point(661, 371)],
            '216' => [new Point(228, 421), new Point(432, 678), new Point(687, 469), new Point(496, 240)],
            '217' => [new Point(382, 177), new Point(243, 476), new Point(529, 584), new Point(664, 272)],
            '218' => [new Point(401, 221), new Point(250, 528), new Point(525, 650), new Point(649, 335)],
            '219' => [new Point(409, 281), new Point(185, 522), new Point(391, 710), new Point(605, 493)],
            '220' => [new Point(197, 478), new Point(394, 691), new Point(632, 458), new Point(422, 247)],
            '221' => [new Point(257, 487), new Point(479, 691), new Point(690, 457), new Point(468, 244)],
            '222' => [new Point(263, 197), new Point(181, 524), new Point(480, 614), new Point(574, 275)], // TODO: Not yet the correct points
            '223' => [new Point(442, 255), new Point(219, 502), new Point(429, 744), new Point(700, 490)],
            '224' => [new Point(246, 354), new Point(417, 661), new Point(704, 530), new Point(567, 255)],
            '225' => [new Point(381, 196), new Point(254, 503), new Point(554, 588), new Point(645, 279)],
            '226' => [new Point(391, 216), new Point(253, 516), new Point(513, 632), new Point(648, 335)],
            '227' => [new Point(141, 476), new Point(317, 701), new Point(583, 489), new Point(405, 266)],
            '228' => [new Point(155, 470), new Point(323, 709), new Point(606, 519), new Point(459, 274)],
            '229' => [new Point(376, 268), new Point(258, 540), new Point(505, 681), new Point(632, 376)],
            '230' => [new Point(214, 470), new Point(414, 668), new Point(664, 419), new Point(452, 224)],
            '231' => [new Point(395, 195), new Point(248, 479), new Point(519, 606), new Point(675, 305)],
            '232' => [new Point(465, 249), new Point(278, 524), new Point(547, 665), new Point(718, 426)],
            '233' => [new Point(456, 260), new Point(203, 474), new Point(407, 717), new Point(678, 476)],
            '234' => [new Point(175, 537), new Point(392, 751), new Point(617, 547), new Point(429, 317)],
            '235' => [new Point(406, 220), new Point(201, 471), new Point(413, 688), new Point(636, 415)],
            '236' => [new Point(293, 173), new Point(202, 486), new Point(497, 562), new Point(593, 253)],
            '237' => [new Point(385, 276), new Point(189, 550), new Point(441, 741), new Point(642, 463)],
            '238' => [new Point(199, 492), new Point(394, 745), new Point(695, 560), new Point(504, 338)],
            '239' => [new Point(359, 158), new Point(252, 463), new Point(537, 553), new Point(649, 264)],
            '240' => [new Point(250, 559), new Point(503, 762), new Point(703, 497), new Point(447, 308)],
            '241' => [new Point(390, 271), new Point(248, 564), new Point(538, 704), new Point(669, 403)],
            '242' => [new Point(324, 257), new Point(178, 568), new Point(464, 680), new Point(600, 364)],
            '243' => [new Point(228, 509), new Point(475, 685), new Point(669, 420), new Point(411, 226)],
            '244' => [new Point(275, 569), new Point(587, 687), new Point(662, 370), new Point(386, 286)],
            '245' => [new Point(231, 546), new Point(522, 608), new Point(637, 291), new Point(333, 231)],
            '246' => [new Point(332, 272), new Point(240, 574), new Point(549, 642), new Point(630, 353)],
            '247' => [new Point(457, 258), new Point(201, 489), new Point(415, 710), new Point(666, 455)],
            '248' => [new Point(427, 250), new Point(290, 591), new Point(563, 683), new Point(688, 358)],
            '249' => [new Point(409, 305), new Point(213, 555), new Point(435, 734), new Point(626, 480)],
            '250' => [new Point(457, 339), new Point(210, 548), new Point(392, 777), new Point(642, 559)],
            '251' => [new Point(461, 308), new Point(229, 536), new Point(429, 739), new Point(657, 505)],
            '252' => [new Point(161, 452), new Point(364, 698), new Point(636, 479), new Point(420, 240)],
            '253' => [],
            '254' => [new Point(461, 196), new Point(208, 395), new Point(398, 657), new Point(636, 453)],
            '255' => [new Point(234, 220), new Point(175, 569), new Point(491, 635), new Point(533, 269)],
            '256' => [new Point(361, 330), new Point(248, 651), new Point(541, 753), new Point(645, 463)],
            '257' => [new Point(225, 481), new Point(427, 721), new Point(660, 475), new Point(434, 278)],
            '258' => [new Point(374, 272), new Point(210, 591), new Point(488, 718), new Point(636, 430)],
            '259' => [new Point(218, 455), new Point(409, 692), new Point(645, 460), new Point(437, 252)],
            '260' => [new Point(383, 233), new Point(265, 566), new Point(548, 673), new Point(664, 353)],
            '261' => [new Point(352, 220), new Point(126, 452), new Point(354, 652), new Point(620, 412)],
            '262' => [new Point(458, 277), new Point(277, 565), new Point(574, 718), new Point(720, 445)],
            '263' => [new Point(138, 402), new Point(290, 673), new Point(612, 513), new Point(456, 260)],
            '264' => [new Point(375, 235), new Point(269, 527), new Point(554, 619), new Point(634, 320)],
            '265' => [new Point(223, 471), new Point(383, 686), new Point(654, 489), new Point(487, 274)],
            '266' => [new Point(407, 207), new Point(252, 493), new Point(488, 633), new Point(665, 364)],
            '267' => [new Point(167, 502), new Point(397, 687), new Point(633, 433), new Point(385, 238)],
            '268' => [new Point(371, 281), new Point(176, 546), new Point(435, 732), new Point(604, 461)],
            '269' => [new Point(442, 316), new Point(191, 525), new Point(347, 777), new Point(634, 595)],
            '270' => [new Point(500, 310), new Point(194, 450), new Point(359, 742), new Point(652, 555)],
            '271' => [new Point(365, 326), new Point(207, 578), new Point(461, 720), new Point(619, 492)],
            '272' => [new Point(421, 289), new Point(163, 536), new Point(352, 766), new Point(644, 513)],
            '273' => [new Point(466, 260), new Point(218, 501), new Point(437, 715), new Point(688, 484)],
            '274' => [new Point(392, 279), new Point(254, 562), new Point(538, 701), new Point(656, 418)],
            '275' => [new Point(395, 260), new Point(234, 545), new Point(493, 708), new Point(667, 386)],
            '276' => [new Point(175, 464), new Point(420, 636), new Point(617, 410), new Point(376, 212)],
            '277' => [new Point(458, 394), new Point(161, 576), new Point(344, 861), new Point(640, 663)],
            '278' => [new Point(490, 274), new Point(223, 459), new Point(411, 726), new Point(668, 523)],
            '279' => [new Point(429, 307), new Point(251, 564), new Point(490, 758), new Point(649, 465)],
            '280' => [new Point(366, 241), new Point(265, 594), new Point(527, 668), new Point(630, 314)],
            '281' => [new Point(197, 515), new Point(408, 688), new Point(627, 469), new Point(403, 291)],
            '282' => [new Point(317, 234), new Point(230, 559), new Point(518, 622), new Point(599, 329)],
            '283' => [new Point(250, 423), new Point(380, 691), new Point(675, 519), new Point(533, 269)],
            '284' => [new Point(331, 199), new Point(233, 512), new Point(512, 612), new Point(611, 282)],
            '285' => [new Point(178, 476), new Point(372, 693), new Point(628, 482), new Point(433, 248)],
            '286' => [new Point(376, 242), new Point(255, 582), new Point(559, 659), new Point(641, 319)],
            '287' => [new Point(207, 482), new Point(312, 740), new Point(627, 585), new Point(476, 333)],
            '288' => [new Point(350, 233), new Point(264, 578), new Point(562, 606), new Point(624, 292)],
            '289' => [new Point(213, 434), new Point(361, 677), new Point(631, 484), new Point(457, 248)],
            '290' => [new Point(339, 252), new Point(241, 572), new Point(531, 649), new Point(624, 342)],
            '291' => [new Point(217, 539), new Point(429, 759), new Point(661, 510), new Point(454, 323)],
            '292' => [new Point(370, 296), new Point(261, 622), new Point(542, 685), new Point(637, 365)],
            '293' => [new Point(490, 308), new Point(265, 527), new Point(455, 747), new Point(688, 546)],
            '294' => [new Point(387, 237), new Point(279, 553), new Point(563, 654), new Point(653, 324)],
            '295' => [new Point(324, 236), new Point(225, 568), new Point(496, 663), new Point(616, 327)],
            '296' => [new Point(364, 261), new Point(218, 495), new Point(500, 635), new Point(625, 382)],
            '297' => [new Point(436, 236), new Point(258, 570), new Point(542, 689), new Point(685, 362)],
            '298' => [new Point(358, 288), new Point(246, 603), new Point(526, 703), new Point(628, 379)],
            '299' => [new Point(225, 534), new Point(423, 739), new Point(665, 539), new Point(431, 313)],
            '300' => [new Point(141, 525), new Point(364, 752), new Point(619, 500), new Point(403, 273)],
            '301' => [new Point(339, 245), new Point(209, 514), new Point(506, 645), new Point(606, 367)],
            '302' => [new Point(210, 420), new Point(375, 644), new Point(664, 429), new Point(487, 221)],
            '303' => [new Point(303, 183), new Point(231, 497), new Point(497, 560), new Point(580, 255)],
            '304' => [new Point(222, 486), new Point(407, 711), new Point(661, 496), new Point(442, 242)],
            '305' => [new Point(406, 221), new Point(152, 488), new Point(403, 722), new Point(656, 443)],
            '306' => [new Point(428, 294), new Point(244, 543), new Point(541, 712), new Point(683, 478)],
            '307' => [new Point(251, 447), new Point(433, 703), new Point(689, 483), new Point(492, 262)],
            '308' => [new Point(478, 285), new Point(302, 576), new Point(563, 731), new Point(759, 446)],
            '309' => [new Point(450, 273), new Point(215, 526), new Point(448, 744), new Point(668, 493)],
            '310' => [new Point(365, 254), new Point(251, 562), new Point(548, 680), new Point(664, 359)],
            '311' => [new Point(230, 477), new Point(418, 737), new Point(683, 524), new Point(511, 273)],
            '312' => [new Point(397, 217), new Point(290, 542), new Point(572, 652), new Point(659, 319)],
            '313' => [new Point(253, 554), new Point(517, 673), new Point(634, 367), new Point(374, 261)],
            '314' => [new Point(346, 269), new Point(266, 588), new Point(535, 676), new Point(646, 347)],
            '315' => [new Point(491, 304), new Point(230, 489), new Point(411, 736), new Point(664, 544)],
            '316' => [new Point(367, 257), new Point(295, 583), new Point(584, 655), new Point(654, 344)],
            '317' => [new Point(246, 520), new Point(451, 742), new Point(712, 511), new Point(485, 290)],
            '318' => [new Point(335, 258), new Point(186, 522), new Point(461, 680), new Point(621, 408)],
            '319' => [new Point(388, 256), new Point(239, 563), new Point(530, 710), new Point(663, 403)],
            '320' => [new Point(158, 478), new Point(388, 698), new Point(594, 421), new Point(408, 225)],
            '321' => [new Point(340, 280), new Point(280, 558), new Point(539, 638), new Point(608, 331)],
            '322' => [new Point(185, 528), new Point(361, 735), new Point(650, 529), new Point(462, 300)],
            '323' => [new Point(436, 241), new Point(229, 506), new Point(457, 683), new Point(656, 432)],
            '324' => [new Point(370, 328), new Point(233, 608), new Point(506, 725), new Point(627, 402)],
            '325' => [new Point(385, 271), new Point(278, 613), new Point(551, 682), new Point(666, 358)],
            '326' => [new Point(282, 551), new Point(484, 758), new Point(698, 561), new Point(481, 334)],
            '327' => [new Point(451, 200), new Point(214, 459), new Point(425, 653), new Point(655, 409)],
            '328' => [new Point(230, 437), new Point(386, 680), new Point(658, 491), new Point(485, 252)],
            '329' => [new Point(349, 193), new Point(205, 488), new Point(480, 624), new Point(622, 320)],
            '330' => [new Point(385, 193), new Point(223, 533), new Point(495, 666), new Point(635, 329)],
            '331' => [new Point(247, 424), new Point(413, 662), new Point(649, 483), new Point(465, 263)],
            '332' => [new Point(369, 236), new Point(242, 544), new Point(511, 645), new Point(643, 343)],
            '333' => [new Point(477, 303), new Point(226, 530), new Point(446, 736), new Point(687, 499)],
            '334' => [new Point(397, 279), new Point(181, 527), new Point(395, 720), new Point(615, 477)],
            '335' => [new Point(161, 532), new Point(394, 721), new Point(626, 454), new Point(379, 271)],
            '336' => [new Point(418, 231), new Point(195, 478), new Point(418, 687), new Point(644, 444)],
            '337' => [new Point(348, 229), new Point(182, 531), new Point(455, 692), new Point(620, 398)],
            '338' => [new Point(215, 456), new Point(408, 712), new Point(669, 509), new Point(474, 259)],
            '339' => [new Point(236, 555), new Point(527, 689), new Point(658, 387), new Point(384, 244)],
            '340' => [new Point(382, 229), new Point(241, 506), new Point(513, 682), new Point(660, 388)],
            '341' => [new Point(247, 514), new Point(465, 747), new Point(688, 514), new Point(468, 288)],
            '342' => [new Point(458, 263), new Point(220, 507), new Point(465, 719), new Point(686, 453)],
            '343' => [new Point(329, 187), new Point(213, 483), new Point(496, 594), new Point(615, 273)],
            '344' => [new Point(389, 263), new Point(237, 557), new Point(507, 699), new Point(659, 430)],
            '345' => [new Point(225, 511), new Point(447, 725), new Point(651, 472), new Point(434, 238)],
            '346' => [new Point(215, 500), new Point(431, 727), new Point(654, 444), new Point(424, 266)],
            '347' => [new Point(263, 586), new Point(527, 724), new Point(720, 466), new Point(462, 297)],
            '348' => [new Point(326, 241), new Point(250, 551), new Point(551, 636), new Point(639, 301)],
            '349' => [new Point(473, 261), new Point(245, 517), new Point(499, 718), new Point(722, 461)],
            '350' => [new Point(465, 301), new Point(210, 528), new Point(430, 748), new Point(684, 506)],
            '351' => [new Point(398, 307), new Point(244, 550), new Point(518, 700), new Point(653, 464)],
            '352' => [new Point(179, 469), new Point(320, 705), new Point(617, 546), new Point(470, 304)],
            '353' => [new Point(147, 481), new Point(291, 724), new Point(585, 565), new Point(437, 326)],
            '354' => [new Point(201, 443), new Point(328, 692), new Point(636, 547), new Point(500, 293)],
            '355' => [new Point(238, 545), new Point(447, 746), new Point(699, 494), new Point(476, 267)],
            '356' => [new Point(336, 259), new Point(211, 531), new Point(501, 677), new Point(626, 401)],
            '357' => [new Point(196, 489), new Point(413, 733), new Point(634, 488), new Point(444, 269)],
            '358' => [new Point(216, 442), new Point(375, 688), new Point(673, 494), new Point(497, 258)],
            '359' => [new Point(379, 310), new Point(241, 602), new Point(510, 727), new Point(656, 428)],
            '360' => [new Point(427, 302), new Point(186, 552), new Point(420, 748), new Point(641, 493)],
            '361' => [new Point(386, 251), new Point(222, 535), new Point(468, 688), new Point(630, 412)],
            '362' => [new Point(349, 210), new Point(213, 515), new Point(477, 652), new Point(624, 340)],
            '363' => [new Point(291, 209), new Point(178, 516), new Point(469, 620), new Point(598, 317)],
            '364' => [new Point(288, 587), new Point(571, 755), new Point(734, 480), new Point(458, 306)],
            '365' => [new Point(211, 506), new Point(470, 695), new Point(630, 386), new Point(389, 231)],
            '366' => [new Point(277, 570), new Point(552, 673), new Point(662, 376), new Point(392, 271)],
            '367' => [new Point(379, 265), new Point(231, 573), new Point(495, 694), new Point(631, 385)],
            '368' => [new Point(477, 259), new Point(277, 538), new Point(505, 701), new Point(706, 402)],
            '369' => [new Point(223, 550), new Point(439, 722), new Point(647, 470), new Point(420, 311)],
            '370' => [new Point(249, 621), new Point(519, 693), new Point(621, 382), new Point(324, 300)],
            '371' => [new Point(364, 255), new Point(256, 596), new Point(556, 691), new Point(670, 376)],
            '372' => [new Point(178, 494), new Point(386, 746), new Point(633, 531), new Point(440, 313)],
            '373' => [new Point(391, 267), new Point(241, 577), new Point(513, 699), new Point(689, 400)],
            '374' => [new Point(243, 511), new Point(479, 745), new Point(694, 493), new Point(486, 274)],
            '375' => [],
            '376' => [new Point(244, 487), new Point(454, 703), new Point(656, 517), new Point(432, 293)],
            '377' => [new Point(195, 420), new Point(365, 696), new Point(679, 499), new Point(488, 262)],
            '378' => [new Point(415, 160), new Point(236, 440), new Point(532, 563), new Point(695, 295)],
            '379' => [new Point(195, 465), new Point(399, 711), new Point(645, 501), new Point(470, 279)],
            '380' => [new Point(418, 228), new Point(264, 545), new Point(533, 665), new Point(688, 340)],
            '381' => [new Point(204, 549), new Point(456, 701), new Point(635, 462), new Point(370, 297)],
            '382' => [new Point(220, 483), new Point(404, 739), new Point(656, 517), new Point(471, 277)],
            '383' => [new Point(386, 277), new Point(254, 605), new Point(556, 702), new Point(659, 384)],
            '384' => [new Point(164, 488), new Point(353, 722), new Point(608, 496), new Point(413, 277)],
            '385' => [new Point(202, 500), new Point(481, 619), new Point(600, 303), new Point(328, 195)],
            '386' => [new Point(340, 237), new Point(216, 528), new Point(489, 647), new Point(616, 360)],
            '387' => [new Point(431, 278), new Point(198, 533), new Point(419, 745), new Point(657, 453)],
            '388' => [new Point(365, 278), new Point(243, 584), new Point(515, 652), new Point(647, 366)],
            '389' => [new Point(338, 294), new Point(237, 595), new Point(527, 680), new Point(614, 370)],
            '390' => [new Point(242, 545), new Point(485, 711), new Point(639, 418), new Point(412, 249)],
            '391' => [new Point(391, 236), new Point(298, 536), new Point(570, 628), new Point(662, 321)],
            '392' => [new Point(184, 541), new Point(392, 742), new Point(630, 497), new Point(418, 299)],
            '393' => [new Point(421, 203), new Point(286, 534), new Point(564, 642), new Point(678, 319)],
            '394' => [new Point(425, 294), new Point(229, 549), new Point(460, 736), new Point(657, 465)],
            '395' => [new Point(357, 339), new Point(236, 645), new Point(514, 761), new Point(635, 452)],
            '396' => [new Point(243, 581), new Point(485, 761), new Point(679, 487), new Point(435, 308)],
            '397' => [new Point(398, 282), new Point(243, 567), new Point(513, 723), new Point(661, 425)],
            '398' => [new Point(221, 512), new Point(440, 717), new Point(667, 488), new Point(481, 288)],
            '399' => [new Point(399, 307), new Point(189, 559), new Point(378, 773), new Point(619, 521)],
            '400' => [new Point(148, 491), new Point(316, 747), new Point(606, 539), new Point(432, 277)],
            '401' => [new Point(336, 311), new Point(272, 576), new Point(589, 651), new Point(633, 371)],
            '402' => [new Point(449, 269), new Point(164, 497), new Point(364, 742), new Point(651, 508)],
            '403' => [new Point(221, 416), new Point(377, 674), new Point(667, 516), new Point(505, 267)],
            '404' => [new Point(335, 243), new Point(249, 550), new Point(537, 635), new Point(620, 347)],
            '405' => [new Point(251, 441), new Point(428, 684), new Point(714, 464), new Point(540, 237)],
            '406' => [new Point(419, 253), new Point(309, 531), new Point(590, 626), new Point(691, 315)],
            '407' => [new Point(358, 223), new Point(271, 544), new Point(549, 624), new Point(634, 322)],
            '408' => [new Point(181, 449), new Point(343, 696), new Point(623, 515), new Point(457, 266)],
            '409' => [new Point(400, 292), new Point(248, 595), new Point(524, 726), new Point(658, 430)],
            '410' => [new Point(164, 527), new Point(329, 771), new Point(613, 563), new Point(438, 323)],
            '411' => [new Point(395, 312), new Point(293, 608), new Point(584, 701), new Point(687, 399)],
            '412' => [new Point(139, 484), new Point(344, 705), new Point(602, 461), new Point(404, 221)],
            '413' => [new Point(428, 278), new Point(189, 488), new Point(404, 710), new Point(654, 478)],
            '414' => [new Point(411, 219), new Point(270, 505), new Point(536, 638), new Point(658, 332)],
            '415' => [new Point(246, 511), new Point(467, 697), new Point(678, 447), new Point(433, 242)],
            '416' => [new Point(353, 263), new Point(237, 564), new Point(542, 672), new Point(654, 372)],
            '417' => [new Point(469, 299), new Point(201, 500), new Point(409, 756), new Point(656, 548)],
            '418' => [new Point(457, 273), new Point(218, 513), new Point(428, 742), new Point(662, 499)],
            '419' => [new Point(355, 203), new Point(215, 503), new Point(487, 637), new Point(614, 344)],
            '420' => [new Point(458, 250), new Point(219, 466), new Point(403, 701), new Point(643, 474)],
            '421' => [new Point(393, 269), new Point(274, 580), new Point(550, 705), new Point(671, 375)],
            '422' => [new Point(398, 269), new Point(254, 570), new Point(527, 713), new Point(666, 407)],
            '423' => [new Point(394, 278), new Point(227, 549), new Point(492, 697), new Point(661, 438)],
            '424' => [new Point(271, 603), new Point(567, 711), new Point(659, 386), new Point(384, 275)],
            '425' => [new Point(368, 300), new Point(253, 636), new Point(527, 753), new Point(648, 397)],
            '426' => [new Point(267, 514), new Point(498, 712), new Point(674, 489), new Point(445, 294)],
            '427' => [new Point(164, 504), new Point(336, 707), new Point(612, 493), new Point(447, 270)],
            '428' => [new Point(363, 223), new Point(251, 528), new Point(512, 639), new Point(658, 327)],
            '429' => [new Point(228, 463), new Point(440, 710), new Point(675, 480), new Point(448, 258)],
            '430' => [new Point(376, 245), new Point(266, 586), new Point(582, 666), new Point(621, 359)],
            '431' => [new Point(245, 531), new Point(455, 697), new Point(672, 424), new Point(422, 253)],
            '432' => [new Point(292, 460), new Point(467, 702), new Point(709, 469), new Point(512, 245)],
            '433' => [new Point(503, 270), new Point(268, 498), new Point(488, 718), new Point(712, 471)],
            '434' => [new Point(236, 572), new Point(487, 710), new Point(617, 404), new Point(390, 286)],
            '435' => [new Point(389, 263), new Point(253, 582), new Point(489, 677), new Point(638, 367)],
            '436' => [new Point(251, 526), new Point(399, 753), new Point(688, 598), new Point(523, 365)],
            '437' => [new Point(385, 243), new Point(253, 568), new Point(531, 673), new Point(673, 357)],
            '438' => [new Point(389, 290), new Point(266, 607), new Point(571, 699), new Point(672, 402)],
            '439' => [new Point(240, 529), new Point(423, 781), new Point(679, 549), new Point(468, 296)],
            '440' => [new Point(213, 482), new Point(453, 694), new Point(644, 431), new Point(416, 227)],
            '441' => [new Point(230, 562), new Point(449, 786), new Point(675, 518), new Point(444, 321)],
            '442' => [new Point(427, 251), new Point(231, 504), new Point(466, 694), new Point(652, 447)],
            '443' => [new Point(363, 291), new Point(259, 611), new Point(550, 720), new Point(649, 392)],
            '444' => [new Point(241, 550), new Point(498, 712), new Point(704, 442), new Point(414, 279)],
            '445' => [new Point(433, 299), new Point(203, 532), new Point(452, 746), new Point(678, 507)],
            '446' => [new Point(397, 306), new Point(258, 626), new Point(560, 734), new Point(669, 434)],
            '447' => [new Point(497, 348), new Point(240, 573), new Point(421, 816), new Point(701, 590)],
            '448' => [new Point(381, 337), new Point(209, 589), new Point(493, 748), new Point(656, 473)],
            '449' => [new Point(446, 317), new Point(237, 579), new Point(479, 768), new Point(690, 506)],
            '450' => [new Point(408, 234), new Point(230, 560), new Point(508, 698), new Point(674, 380)],
            '451' => [new Point(268, 477), new Point(466, 703), new Point(682, 510), new Point(471, 281)],
            '452' => [new Point(140, 536), new Point(362, 772), new Point(623, 571), new Point(405, 311)],
            '453' => [new Point(181, 534), new Point(413, 783), new Point(668, 499), new Point(446, 309)],
            '454' => [new Point(343, 278), new Point(197, 567), new Point(453, 699), new Point(608, 435)],
            '455' => [new Point(232, 552), new Point(444, 782), new Point(656, 536), new Point(413, 305)],
            '456' => [new Point(209, 549), new Point(458, 785), new Point(687, 510), new Point(484, 335)],
            '457' => [new Point(324, 303), new Point(262, 631), new Point(530, 689), new Point(601, 376)],
            '458' => [new Point(371, 337), new Point(279, 657), new Point(550, 753), new Point(663, 417)],
            '459' => [new Point(388, 248), new Point(208, 517), new Point(458, 692), new Point(648, 444)],
            '460' => [new Point(344, 310), new Point(150, 593), new Point(443, 761), new Point(622, 467)],
            '461' => [new Point(424, 340), new Point(251, 617), new Point(546, 756), new Point(692, 465)],
            '462' => [new Point(441, 313), new Point(211, 573), new Point(444, 747), new Point(658, 491)],
            '463' => [new Point(288, 497), new Point(484, 696), new Point(715, 484), new Point(513, 285)],
            '464' => [new Point(337, 219), new Point(255, 549), new Point(531, 628), new Point(611, 297)],
            '465' => [new Point(339, 274), new Point(246, 582), new Point(521, 691), new Point(617, 364)],
            '466' => [new Point(447, 352), new Point(263, 646), new Point(513, 807), new Point(689, 516)],
            '467' => [new Point(336, 261), new Point(224, 547), new Point(497, 668), new Point(626, 381)],
            '468' => [new Point(199, 496), new Point(400, 742), new Point(689, 513), new Point(463, 273)],
            '469' => [new Point(211, 628), new Point(516, 783), new Point(654, 483), new Point(412, 355)],
            '470' => [new Point(382, 351), new Point(293, 671), new Point(577, 735), new Point(670, 430)],
            '471' => [new Point(452, 343), new Point(252, 588), new Point(477, 796), new Point(681, 552)],
            '472' => [new Point(356, 295), new Point(213, 624), new Point(505, 747), new Point(637, 423)],
            '473' => [new Point(305, 256), new Point(221, 562), new Point(521, 647), new Point(589, 324)],
            '474' => [new Point(317, 303), new Point(245, 627), new Point(526, 705), new Point(595, 346)],
            '475' => [new Point(188, 512), new Point(357, 740), new Point(638, 571), new Point(489, 326)],
            '476' => [new Point(353, 325), new Point(273, 598), new Point(552, 683), new Point(645, 404)],
            '477' => [new Point(151, 507), new Point(387, 752), new Point(648, 501), new Point(409, 308)],
            '478' => [new Point(239, 607), new Point(529, 711), new Point(680, 397), new Point(405, 270)],
            '479' => [new Point(261, 555), new Point(547, 676), new Point(671, 375), new Point(395, 277)],
            '480' => [new Point(352, 346), new Point(235, 644), new Point(512, 760), new Point(646, 471)],
            '481' => [new Point(294, 319), new Point(195, 660), new Point(500, 760), new Point(617, 427)],
            '482' => [new Point(223, 527), new Point(461, 770), new Point(688, 551), new Point(445, 296)],
            '483' => [new Point(353, 266), new Point(252, 602), new Point(593, 702), new Point(685, 342)],
            '484' => [new Point(241, 464), new Point(485, 720), new Point(709, 507), new Point(467, 255)],
            '485' => [new Point(371, 269), new Point(202, 567), new Point(515, 734), new Point(670, 437)],
            '486' => [new Point(214, 539), new Point(424, 816), new Point(695, 593), new Point(468, 330)],
            '487' => [new Point(459, 338), new Point(285, 618), new Point(598, 780), new Point(753, 496)],
            '488' => [new Point(150, 489), new Point(355, 759), new Point(638, 539), new Point(413, 316)],
            '489' => [new Point(210, 523), new Point(398, 785), new Point(678, 627), new Point(505, 360)],
            '490' => [new Point(432, 275), new Point(240, 553), new Point(507, 732), new Point(702, 461)],
            '491' => [new Point(251, 560), new Point(564, 677), new Point(689, 353), new Point(366, 243)],
            '492' => [new Point(407, 346), new Point(301, 648), new Point(641, 737), new Point(726, 448)],
            '493' => [new Point(174, 558), new Point(365, 832), new Point(644, 628), new Point(473, 347)],
            '494' => [new Point(415, 321), new Point(213, 574), new Point(441, 824), new Point(686, 551)],
            '495' => [new Point(218, 481), new Point(454, 749), new Point(675, 559), new Point(467, 288)],
            '496' => [new Point(327, 293), new Point(177, 570), new Point(462, 757), new Point(630, 455)],
            '497' => [new Point(265, 556), new Point(486, 825), new Point(760, 605), new Point(537, 343)],
            '498' => [new Point(362, 354), new Point(263, 670), new Point(595, 773), new Point(692, 448)],
            '499' => [new Point(394, 298), new Point(212, 616), new Point(514, 790), new Point(683, 508)],
            '500' => [new Point(409, 245), new Point(172, 464), new Point(400, 753), new Point(675, 488)],
            '501' => [new Point(230, 509), new Point(493, 761), new Point(688, 578), new Point(456, 324)],
        ];

        foreach ($corners as $pieceIndex => $cornerPoints) {
            $piece = Piece::fromSerialized(file_get_contents(__DIR__ . '/../../resources/Fixtures/Piece/piece' . $pieceIndex . '_piece.ser'));

            $foundCorners = array_filter($piece->getBorderPoints(), fn (Point $point): bool => $point instanceof DerivativePoint && $point->isUsedAsCorner());
            $foundCorners = array_values(array_map(fn (DerivativePoint $point): Point => new Point($point->getX(), $point->getY()), $foundCorners));

            $tooMuchAberration = false;
            foreach ($cornerPoints as $index => $cornerPoint) {
                if (abs($cornerPoint->getX() - $foundCorners[$index]->getX()) > 1 || abs($cornerPoint->getY() - $foundCorners[$index]->getY()) > 1) {
                    $tooMuchAberration = true;
                    break;
                }
            }

            if ($tooMuchAberration) {
                $this->assertEquals(
                    array_map(fn(Point $point): array => $point->jsonSerialize(), $cornerPoints),
                    array_map(fn(Point $point): array => $point->jsonSerialize(), $foundCorners),
                    'Piece #' . $pieceIndex
                );
            }
        }

        $this->assertTrue(true);
    }
}

<?php namespace Core\Format;
/**
 * Complex and powerful class to render differences to HTML.
 *
 * Usage: $diff = new Diff($from, $to, Diff::DEPTH_CHARACTERS);
 * $output = $diff->toLines(2); - Render only different lines, with 2 lines above/below.
 */
class Diff
{
    /**
     * How deep you want to seek.
     */
    const DEPTH_LINES = 1;
    const DEPTH_WORDS = 2;
    const DEPTH_CHARACTERS = 3;
    /**
     * Various states.
     */
    const STATE_IDENTICAL = 0;
    const STATE_INSERTED = 1;
    const STATE_DELETED = 2;
    const STATE_CHANGED = 3;
    const MAX_DEPTH = 3;
    /**
     * The split characters based on depth.
     *
     * Ie. On level 1 we seperate by lines, level 2 by words, level 3 by characters.
     * @var array
     */
    private static $_splitCharacters = array(
        self::DEPTH_LINES => "\n",
        self::DEPTH_WORDS => ' ',
        self::DEPTH_CHARACTERS => '',
    );
    /**
     * The html objects used for this state.
     * @var array
     */
    private static $_stateHtml = array(
        self::STATE_IDENTICAL => '',
        self::STATE_INSERTED => 'ins',
        self::STATE_DELETED => 'del',
        self::STATE_CHANGED => 'adj',
    );
    /**
     * Current state.
     * @var int
     */
    private $_state = 0;
    /**
     * Current string.
     * @var string
     */
    private $_string = null;
    /**
     * The current states.
     * @var array
     */
    private $_states = array();
    /**
     * Current depth.
     * @var int
     */
    private $_depth = 0;
    /**
     * Similarity from 0..1
     * @var float
     */
    private $_similarity = 0;
    /**
     * If visible (with lines).
     * @var boolean
     */
    public $visible = true;

    /**
     * Basic constructer, used for every level.
     *
     * It does basic comparisons, which are much faster than the in-depth finding of differences.
     * @param string $from
     * @param string $to
     * @param int $recurseDepth
     * @param int $depth
     */
    public function __construct($from, $to, $recurseDepth = self::DEPTH_LINES, $depth = self::DEPTH_LINES)
    {
        $this->_depth = $depth;
        $emptyFrom = empty($from);
        $emptyTo = empty($to);
        if ($from == $to) {
            $this->_string = $from;
            $this->_state = self::STATE_IDENTICAL;
            $this->_similarity = 1;
        } else if (!$emptyFrom && $emptyTo) {
            $this->_string = $from;
            $this->_state = self::STATE_DELETED;
        } else if ($emptyFrom && !$emptyTo) {
            $this->_string = $to;
            $this->_state = self::STATE_INSERTED;
        } else if ($recurseDepth > self::MAX_DEPTH || $recurseDepth == 0) {
            // If we do not recurse deeper, the strings have simply been changed.
            $this->_insertState(0, $from, null);
            $this->_insertState(0, null, $to);
        } else {
            $this->_string = null;
            // Find the differences.
            $this->_state = $this->_findDifference($from, $to, $recurseDepth);
        }
    }

    /**
     * Get the state of the current object.
     *
     * Possible outcomes are 0 (identical), 1 (inserted), 2 (deleted) and 3 (changed).
     *
     * @return int
     */
    public function getState()
    {
        return $this->_state;
    }

    /**
     * Get similarity, from 0 (no similarity) to 1 (identical).
     *
     * @return float
     */
    public function getSimilarity()
    {
        return $this->_similarity;
    }

    /**
     * Split the string (if allowed) and find the difference.
     * @param string $from
     * @param string $to
     * @param int $recurseDepth
     */
    private function _findDifference($from, $to, $recurseDepth)
    {
        $splitChar = getKey(self::$_splitCharacters, $this->_depth, '');
        $leftParts = empty($splitChar) ? str_split($from) : explode($splitChar, $from);
        $rightParts = empty($splitChar) ? str_split($to) : explode($splitChar, $to);
        $leftIndex = 0;
        $rightIndex = 0;
        $leftMax = count($leftParts);
        $rightMax = count($rightParts);
        while ($leftIndex < $leftMax) {
            // Find the first common occurence between left and right.
            $foundIndexes = $this->_findFirstCommon($leftParts, $leftIndex, $rightParts, $rightIndex, $recurseDepth);
            $leftIndexFound = $foundIndexes['L'];
            $rightIndexFound = $foundIndexes['R'];
            // Add differences for the states between the common occurence.
            $leftIndex += $this->_addStates(false, $leftParts, $leftIndex, $leftIndexFound);
            $rightIndex += $this->_addStates(true, $rightParts, $rightIndex, $rightIndexFound);
            // If we have a common occurence.
            if ($leftIndexFound < $leftMax && $rightIndexFound < $rightMax) {
                //Go one level deeper.
                $this->_insertState(
                    $leftParts[$leftIndexFound],
                    $rightParts[$rightIndexFound],
                    $recurseDepth - 1,
                    $this->_depth + 1
                );
                $leftIndex++;
                $rightIndex++;
            } else {
                break;
            }
        }
        //Add remaining
        while ($leftIndex < $leftMax) {
            $this->_insertState($leftParts[$leftIndex], null);
            $leftIndex++;
        }
        while ($rightIndex < $rightMax) {
            $this->_insertState(null, $rightParts[$rightIndex]);
            $rightIndex++;
        }
        $this->_similarity = $this->_calculateSimilarity(max($leftMax, $rightMax));
        return self::STATE_CHANGED;
    }

    /**
     * Calculate the similarity.
     *
     * To get a more honest result, we use the max of left and right, not the total amount of states.
     * @return float
     */
    private function _calculateSimilarity($total)
    {
        $result = 0;
        foreach ($this->_states as $state) {
            $result += $state->getSimilarity();
        }
        return $result / $total;
    }

    /**
     * Find the first common string.
     *
     * This function runs the comparison twice, biased on left and biased on right. This avoids having to do a complete
     * recursive comparison and finding the 'best' solution. It's not perfect but it is MUCH faster.
     *
     * @param array $leftParts
     * @param int $leftIndex
     * @param array $rightParts
     * @param int $rightIndex
     * @param int $recurseDepth
     * @return array
     */
    private function _findFirstCommon($leftParts, $leftIndex, $rightParts, $rightIndex, $recurseDepth)
    {
        // Take the array FROM the index, to prevent seeking 'back'. We keep keys intact.
        $left = array_slice($leftParts, $leftIndex, null, true);
        $right = array_slice($rightParts, $rightIndex, null, true);
        if (count($left) > 0 && count($right) > 0) {
            //We seek twice, left and right, and use the 'earliest' common.
            if ($recurseDepth > 1 && $this->_depth < self::MAX_DEPTH) {
                $resultLeft = $this->_compareDeep($left, $right);
                $resultRight = $this->_compareDeep($right, $left);
            } else {
                $resultLeft = $this->_compareFlat($left, $right);
                $resultRight = $this->_compareFlat($right, $left);
            }
            $result = $this->_getNicestResult($resultLeft, $resultRight);
        } else {
            $result = array('L' => false, 'R' => false);
        }
        if ($result['L'] === false) {
            $result['L'] = count($leftParts);
        }
        if ($result['R'] === false) {
            $result['R'] = count($rightParts);
        }
        return $result;
    }

    /**
     * Flat compare, when we cannot compare deeper.
     *
     * This does an unstrict compare and finds the key. Pretty fast as it uses C functions.
     * @param array $left
     * @param array $right
     * @return array
     */
    private function _compareFlat($left, $right)
    {
        foreach ($left as $leftIndex => $string) {
            $rightIndex = array_search($string, $right);
            if ($rightIndex !== false) {
                return array('L' => $leftIndex, 'R' => $rightIndex);
            }
        }
        return false;
    }

    /**
     * Deep compare, finds strings that are 'similar'.
     *
     * Identical strings are of course caught early for performance.
     *
     * @param array $left
     * @param array $right
     * @return array
     */
    private function _compareDeep($left, $right)
    {
        foreach ($left as $leftIndex => $leftString) {
            foreach ($right as $rightIndex => $rightString) {
                if ($leftString == $rightString) {
                    return array('L' => $leftIndex, 'R' => $rightIndex);
                }
                $diff = new self($leftString, $rightString, 1, $this->_depth + 1);
                if ($diff->getSimilarity() > 0.5) {
                    return array('L' => $leftIndex, 'R' => $rightIndex);
                }
            }
        }
        return false;
    }

    /**
     * Get "nicest" result.
     *
     * A result is nicer, if the average value of the found indexes is higher.
     *
     * This avoids words being moved forwards to flag the whole text as removed.
     * @param array $resultLeft
     * @param array $resultRight
     * @result array The best of the two.
     */
    private function _getNicestResult($resultLeft, $resultRight)
    {
        $emptyLeft = empty($resultLeft);
        $emptyRight = empty($resultRight);
        if ($emptyLeft && $emptyRight) {
            $result = array('L' => false, 'R' => false);
        } else if ($emptyLeft || $emptyRight) {
            $result = $emptyRight ? $resultLeft : $this->_resultSwitch($resultRight);
        } else {
            $averageLeft = ($resultLeft['L'] + $resultLeft['R']) / 2;
            $averageRight = ($resultRight['L'] + $resultRight['R']) / 2;
            $result = $averageLeft <= $averageRight ? $resultLeft : $this->_resultSwitch($resultRight);
        }
        return $result;
    }

    /**
     * Switch left and right.
     * @param array $resultRight
     * @return array
     */
    private function _resultSwitch($resultRight)
    {
        return array('L' => $resultRight['R'], 'R' => $resultRight['L']);
    }

    /**
     * Add states, flagging them as inserted (or deleted).
     * @param boolen $inserted
     * @param array $states
     * @param int $startIndex
     * @param int $endIndex
     */
    private function _addStates($inserted, $states, $startIndex, $endIndex)
    {
        $safeEndIndex = min(count($states), $endIndex);
        $diff = $safeEndIndex - $startIndex;
        if ($diff < 0) {
            throw new \Exception("Something wrong with indexes! StartIndex: $startIndex - EndIndex $endIndex - SafeIndex $safeEndIndex");
        }
        if ($diff > 0 && $inserted) {
            for ($i = 0; $i < $diff; $i++) {
                $index = $startIndex + $i;
                $this->_insertState(null, $states[$index]);
            }
        } else if ($diff > 0 && !$inserted) {
            for ($i = 0; $i < $diff; $i++) {
                $index = $startIndex + $i;
                $this->_insertState($states[$index], null);
            }
        }
        return $diff;
    }

    /**
     * Insert a single state into the array.
     * @param string $from
     * @param string $to
     * @param int $recurseDepth
     * @param int $depth
     * @return Diff
     */
    private function _insertState($from, $to, $recurseDepth = 0, $depth = 0)
    {
        $newDepth = $depth ? : $this->_depth;
        $state = new self($from, $to, $recurseDepth, $newDepth);
        $this->_states[] = $state;
        $this->_state = self::STATE_CHANGED;
        return $state;
    }

    /**
     * Render to lines of difference.
     * @param type $lines
     */
    public function toLines($lines = 1)
    {
        $result = 'Fully identical.';
        if (!empty($this->_states)) {
            $stillVisible = 0;
            foreach ($this->_states as $index => $state) {
                $state->visible = false;
                if ($state->getState() != self::STATE_IDENTICAL) {
                    if ($stillVisible == 0) {
                        for ($i = 1; $i <= $lines; $i++) {
                            $curIndex = max($index - $i, 0);
                            $this->_states[$curIndex]->visible = true;
                        }
                    }
                    $stillVisible = $lines + 1;
                }
                if ($stillVisible > 0) {
                    $state->visible = true;
                    $stillVisible--;
                }
            }
            $result = strval($this);
        } else if ($this->_state > self::STATE_IDENTICAL) {
            $result = strval($this);
        }
        return $result;
    }

    /**
     * Render lines.
     *
     * This is the first level when depth is lines.
     *
     * Renders HTML to Ordered List (ol) with offset.
     * @return array
     */
    protected function _renderLines()
    {
        $result = array();
        $showedEmpty = false;
        $result[] = '<div class="diff-output">';
        $result[] = '<ol>';
        foreach ($this->_states as $index => $state) {
            if ($state->visible) {
                if ($showedEmpty) {
                    $result[] = '</ol><ol start="' . ($index + 1) . '">';
                }
                $showedEmpty = false;
                $class = ($state->getState() > 0) ? ' class="diff-changed"' : '';
                $result[] = "<li{$class}>" . str_replace('  ', '&nbsp; ', $state) . '</li>';
            } else if (!$showedEmpty) {
                $showedEmpty = true;
            }
        }
        $result[] = '</ol>';
        $result[] = '</diff>';
        return $result;
    }

    /**
     * Render to html string.
     * @return string
     */
    public function __toString()
    {
        $result = array();
        if (!empty($this->_states)) {
            if ($this->_depth == self::DEPTH_LINES) {
                $result = $this->_renderLines();
            } else {
                foreach ($this->_states as $state) {
                    $result[] = strval($state);
                }
            }
        } else {
            $domObj = getKey(self::$_stateHtml, $this->_state, '');
            $domStart = !$domObj ? '' : "<{$domObj}>";
            $domEnd = !$domObj ? '' : "</{$domObj}>";
            $result[] = $domStart . $this->_string . $domEnd;
        }
        $joinChar = getKey(self::$_splitCharacters, $this->_depth, '');
        return implode($joinChar, $result);
    }
}
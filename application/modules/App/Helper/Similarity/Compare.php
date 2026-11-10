<?php

namespace App\Helper\Similarity;

class Compare
{
    public function all($first, $second)
    {
        $similar = $this->similarText($first, $second);
        $smg = $this->smg($first, $second);
        $jaroWinkler = $this->jaroWinkler($first, $second);
        $levenshtein = $this->levenshtein($first, $second);

        return [
            'SimilarText' => $similar,
            'SmithWatermanGotoh' => $smg,
            'JaroWinkler' => $jaroWinkler,
            'Levenshtein' => $levenshtein,
        ];
    }

    /**
     * Run a basic levenshtein comparison using PHP's built-in function
     *
     * @param string $first First string to compare
     * @param string $second Second string to compare
     *
     * @return string Returns the phrase passed in
     */
    public function levenshtein($first, $second)
    {
        $distance = (new Levenshtein())->compare($first, $second);
        return (1 - $distance / max(strlen($first), strlen($second))) * 100;
    }

    public function jaroWinkler($first, $second)
    {
        $distance = (new JaroWinkler())->compare($first, $second);
        return $distance * 100;
    }

    public function smg($first, $second)
    {
        $distance = (new SmithWatermanGotoh())->compare($first, $second);
        return $distance * 100;
    }

    public function similarText($first, $second)
    {
        similar_text($first, $second, $percent);
        return $percent;
    }
}

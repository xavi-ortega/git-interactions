<?php

namespace App\Helpers;

class GitParser
{

    private $index;
    private $text;

    public function __construct(string $test)
    {
        $this->index = 0;
        $this->test = $test;
    }

    public function hasNext()
    {
        return $this->index < mb_strlen($this->text);
    }

    public function expect($type, $token)
    {
        if (!$this->accept($type, $token)) {
            throw new Exception("Expected " + $token + " at " + $this->toString());
        }

        return $this;
    }

    public function skip($type, $token)
    {
        $this->accept($type, $token);
        return $this;
    }

    public function skipUntil($type, $token)
    {
        $this->acceptUntil($type, $token);
        return $this;
    }

    public function skipSpaces()
    {
        return $this->skip('regexp', '/^\s+/');
    }

    public function acceptUntil($type, $token)
    {
        $result = '';

        while (!$this->peek($type, $token) && $this->hasNext()) {
            $result += $this->acceptChar();
        }

        return $result;
    }

    public function accept($type, $token)
    {
        switch ($type) {
            case 'string':
                return $this->acceptString($token);

            case 'regexp':
                return $this->acceptRegexp($token);

            case 'char':
                return $this->acceptChar();
        }
    }


    public function peek($type, $token)
    {
        switch ($type) {
            case 'string':
                return $this->matchString($token);

            case 'regexp':
                return $this->matchRegexp($token);

            case 'char':
                return $this->matchChar();
        }
    }

    private function acceptString(string $str): string
    {
        if ($this->matchString($str)) {
            $this->index += mb_strlen($str);
            return $str;
        }

        return null;
    }

    private function matchString(string $str): string
    {
        $start = $this->index;
        $end = mb_strlen($str) + $this->index;
        $present = mb_substr($this->text, $start, $end);

        return ($present === $str && $str) || null;
    }

    private function acceptRegExp(string $pattern)
    {
        $match = $this->matchRegExp($pattern);
        if ($match) {
            $this->index += mb_strlen($match);
            return $match;
        }

        return null;
    }

    private function matchRegExp(string $pattern): string
    {
        $matchingText = mb_substr($this->text, $this->index);
        $match = [];
        preg_match($pattern, $matchingText, $match);

        if ($match && count($match) > 0) {
            return $match[0];
        } else {
            return null;
        }
    }

    private function acceptChar()
    {
        $char = $this->matchChar();
        $this->index++;
        return $char;
    }

    private function matchChar()
    {
        return $this->text[$this->index];
    }

    public function toString()
    {
        $substr = mb_substr($this->text, max(0, $this->index - 30), $this->index);
        $firstHalf = '';
        preg_replace('/\n/g', $firstHalf, $substr);

        $substr = mb_substr($this->text, $this->index, min($this->index + 30, mb_strlen($this->text)));
        $secondHalf = '';
        preg_replace('/\n/g', $secondHalf, $substr);

        return '>' + $firstHalf + '·' + $secondHalf;
    }
}

<?php
/**
 * Handlebars tokenizer (based on mustache)
 *
 * @category  Xamin
 * @package   Handlebars
 * @author    Justin Hileman <dontknow@example.org>
 * @author    fzerorubigd <fzerorubigd@gmail.com>
 * @author    Behrooz Shabani <everplays@gmail.com>
 * @author    Mardix <https://github.com/mardix>
 * @copyright 2012 (c) ParsPooyesh Co
 * @copyright 2013 (c) Behrooz Shabani
 * @copyright 2013 (c) Mardix
 * @license   MIT
 * @link      http://voodoophp.org/docs/handlebars
 */

namespace Handlebars;

class Tokenizer
{

    // Finite state machine states
    const IN_TEXT = 0;
    const IN_TAG_TYPE = 1;
    const IN_TAG = 2;

    // Token types
    const T_SECTION = '#';
    const T_INVERTED = '^';
    const T_END_SECTION = '/';
    const T_COMMENT = '!';
    // XXX: remove partials support from tokenizer and make it a helper?
    const T_PARTIAL = '>';
    const T_PARTIAL_2 = '<';
    const T_DELIM_CHANGE = '=';
    const T_ESCAPED = '€'; // was _v - why? breaks T_TEXT & T_GETTEXT
    const T_UNESCAPED = '{';
    const T_UNESCAPED_2 = '&';
    const T_TEXT = '|'; //  was _t - why? breaks T_ESCAPED & T_GETTEXT
    const T_GETTEXT = '_';
    const T_NGETTEXT = 'ngettext';

    // Handlebars tokens can be escaped: \{{
    const ESCAPE_CHAR = '\\';

    // Valid token types
    private $tagTypes = [
        self::T_SECTION => true,
        self::T_INVERTED => true,
        self::T_END_SECTION => true,
        self::T_COMMENT => true,
        self::T_PARTIAL => true,
        self::T_PARTIAL_2 => true,
        self::T_DELIM_CHANGE => true,
        self::T_ESCAPED => true,
        self::T_UNESCAPED => true,
        self::T_UNESCAPED_2 => true
    ];

    // Interpolated tags
    private $interpolatedTags = [
        self::T_ESCAPED => true,
        self::T_UNESCAPED => true,
        self::T_UNESCAPED_2 => true,
        self::T_GETTEXT => true,
        self::T_NGETTEXT => true
    ];

    // Token properties
    const TYPE = 'type';
    const NAME = 'name';
    const OTAG = 'otag';
    const CTAG = 'ctag';
    const INDEX = 'index';
    const END = 'end';
    const INDENT = 'indent';
    const NODES = 'nodes';
    const VALUE = 'value';
    const ARGS = 'args';

    protected $state;
    protected $tagType;
    protected $tag;
    protected $buffer;
    protected $tokens;
    protected $seenTag;
    protected $lineStart;
    protected $otag;
    protected $ctag;

    /**
     * Mustache tokenizer constructor.
     *
     * @param array $options Options for the context. It may contain the following: (default: empty array)
     *                       enableGettext => Boolean, Enables gettext support (default: false)
     *
     * @throws InvalidArgumentException when calling this method when enableGettext is not a boolean.
     */
    public function __construct($options = [])
    {
        if (isset($options[Handlebars::OPTION_ENABLE_GETTEXT])) {
            if (!is_bool($options[Handlebars::OPTION_ENABLE_GETTEXT])) {
                throw new InvalidArgumentException(
                    'Context Constructor "' . Handlebars::OPTION_ENABLE_GETTEXT . '" option must be a boolean'
                );
            }
            $this->enableGettext = $options[Handlebars::OPTION_ENABLE_GETTEXT];
            if ($this->enableGettext) {
                $this->tagTypes[self::T_GETTEXT] = true;
                $this->tagTypes[self::T_NGETTEXT] = true;
            }

        }
    }


    /**
     * Scan and tokenize template source.
     *
     * @param string $text       Mustache template source to tokenize
     * @param string $delimiters Optional, pass opening and closing delimiters
     *
     * @return array Set of Mustache tokens
     */
    public function scan($text, $delimiters = null)
    {
        if ($text instanceof HandlebarsString) {
            $text = $text->getString();
        }

        $this->reset();

        if ($delimiters = trim($delimiters)) {
            list($otag, $ctag) = explode(' ', $delimiters);
            $this->otag = $otag;
            $this->ctag = $ctag;
        }

        $openingTagLength = strlen($this->otag);
        $closingTagLength = strlen($this->ctag);
        $firstOpeningTagCharacter = $this->otag[0];
        $firstClosingTagCharacter = $this->ctag[0];

        $len = strlen($text);

        for ($i = 0; $i < $len; $i++) {

            $character = $text[$i];

            switch ($this->state) {

                case self::IN_TEXT:

                    // escape char found, check if token follows
                    // and skip if so
                    if ($character == self::ESCAPE_CHAR && $text[$i+1] === $firstOpeningTagCharacter) {
                        $this->buffer .= $text[$i+1];
                        $i++;
                        break;
                    }

                    if ($character === $firstOpeningTagCharacter && $this->tagChange($this->otag, $text, $i, $openingTagLength)
                    ) {
                        $i--;
                        $this->flushBuffer();
                        $this->state = self::IN_TAG_TYPE;
                    } else {
                        if ($character == "\n") {
                            $this->filterLine();
                        } else {
                            $this->buffer .= $character;
                        }
                    }
                    break;

                case self::IN_TAG_TYPE:
                    $i += $openingTagLength - 1;
                    if (isset($this->tagTypes[$text[$i + 1]])) {
                        $tag = $text[$i + 1];
                        $this->tagType = $tag;
                    } else {
                        $tag = null;
                        $this->tagType = self::T_ESCAPED;
                    }

                    if ($this->tagType === self::T_DELIM_CHANGE) {
                        $i = $this->changeDelimiters($text, $i);
                        $openingTagLength = strlen($this->otag);
                        $closingTagLength = strlen($this->ctag);
                        $firstOpeningTagCharacter = $this->otag[0];
                        $firstClosingTagCharacter = $this->ctag[0];

                        $this->state = self::IN_TEXT;
                    } else {
                        if ($tag !== null) {
                            $i++;
                        }
                        $this->state = self::IN_TAG;
                    }
                    $this->seenTag = $i;
                    break;

                default:
                    if ($character === $firstClosingTagCharacter && $this->tagChange($this->ctag, $text, $i, $closingTagLength)) {
                        if ($this->enableGettext) {
                            // Check for ngettext, longer than usual tagname
                            $newBuffer = explode(' ', trim($this->buffer), 2);
                            if ((count($newBuffer) == 2) && ($newBuffer[0] == self::T_NGETTEXT)) {
                                $this->tagType = self::T_NGETTEXT;
                            }
                        }
                        // Sections (Helpers) can accept parameters
                        // Same thing for Partials (little known fact)
                        // As well as for ngettext
                        if (in_array($this->tagType, [
                                self::T_SECTION,
                                self::T_PARTIAL,
                                self::T_PARTIAL_2,
                                self::T_NGETTEXT]
                        )) {
                            $newBuffer = explode(' ', trim($this->buffer), 2);
                            $args = '';
                            if (count($newBuffer) == 2) {
                                $args = $newBuffer[1];
                            }
                            $this->buffer = $newBuffer[0];
                        }
                        $t = [
                            self::TYPE => $this->tagType,
                            self::NAME => trim($this->buffer),
                            self::OTAG => $this->otag,
                            self::CTAG => $this->ctag,
                            self::INDEX => ($this->tagType == self::T_END_SECTION) ?
                                $this->seenTag - $openingTagLength :
                                $i + strlen($this->ctag),
                        ];
                        if (isset($args)) {
                            $t[self::ARGS] = $args;
                        }
                        $this->tokens[] = $t;
                        unset($t);
                        unset($args);
                        $this->buffer = '';
                        $i += strlen($this->ctag) - 1;
                        $this->state = self::IN_TEXT;
                        if ($this->tagType == self::T_UNESCAPED) {
                            if ($this->ctag == '}}') {
                                $i++;
                            } else {
                                // Clean up `{{{ tripleStache }}}` style tokens.
                                $lastIndex = count($this->tokens) - 1;
                                $lastName = $this->tokens[$lastIndex][self::NAME];
                                if (substr($lastName, -1) === '}') {
                                    $this->tokens[$lastIndex][self::NAME] = trim(
                                        substr($lastName, 0, -1)
                                    );
                                }
                            }
                        }
                    } else {
                        $this->buffer .= $character;
                    }
                    break;
            }
        }

        $this->filterLine(true);

        return $this->tokens;
    }

    /**
     * Helper function to reset tokenizer internal state.
     *
     * @return void
     */
    protected function reset()
    {
        $this->state = self::IN_TEXT;
        $this->tagType = null;
        $this->tag = null;
        $this->buffer = '';
        $this->tokens = [];
        $this->seenTag = false;
        $this->lineStart = 0;
        $this->otag = '{{';
        $this->ctag = '}}';
    }

    /**
     * Flush the current buffer to a token.
     *
     * @return void
     */
    protected function flushBuffer()
    {
        if (!empty($this->buffer)) {
            $this->tokens[] = [
                self::TYPE => self::T_TEXT,
                self::VALUE => $this->buffer
            ];
            $this->buffer = '';
        }
    }

    /**
     * Test whether the current line is entirely made up of whitespace.
     *
     * @return boolean True if the current line is all whitespace
     */
    protected function lineIsWhitespace()
    {
        $tokensCount = count($this->tokens);
        for ($j = $this->lineStart; $j < $tokensCount; $j++) {
            $token = $this->tokens[$j];
            if (isset($this->tagTypes[$token[self::TYPE]])) {
                if (isset($this->interpolatedTags[$token[self::TYPE]])) {
                    return false;
                }
            } elseif ($token[self::TYPE] == self::T_TEXT) {
                if (preg_match('/\S/', $token[self::VALUE])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Filter out whitespace-only lines and store indent levels for partials.
     *
     * @param bool $noNewLine Suppress the newline? (default: false)
     *
     * @return void
     */
    protected function filterLine($noNewLine = false)
    {
        $this->flushBuffer();
        if ($this->seenTag && $this->lineIsWhitespace()) {
            $tokensCount = count($this->tokens);
            for ($j = $this->lineStart; $j < $tokensCount; $j++) {
                if ($this->tokens[$j][self::TYPE] == self::T_TEXT) {
                    if (isset($this->tokens[$j + 1])
                        && $this->tokens[$j + 1][self::TYPE] == self::T_PARTIAL
                    ) {
                        $this->tokens[$j + 1][self::INDENT]
                            = $this->tokens[$j][self::VALUE];
                    }

                    $this->tokens[$j] = null;
                }
            }
        } elseif (!$noNewLine) {
            $this->tokens[] = [self::TYPE => self::T_TEXT, self::VALUE => "\n"];
        }

        $this->seenTag = false;
        $this->lineStart = count($this->tokens);
    }

    /**
     * Change the current Mustache delimiters. Set new `otag` and `ctag` values.
     *
     * @param string $text  Mustache template source
     * @param int    $index Current tokenizer index
     *
     * @return int New index value
     */
    protected function changeDelimiters($text, $index)
    {
        $startIndex = strpos($text, '=', $index) + 1;
        $close = '=' . $this->ctag;
        $closeIndex = strpos($text, $close, $index);

        list($otag, $ctag) = explode(
            ' ',
            trim(substr($text, $startIndex, $closeIndex - $startIndex))
        );
        $this->otag = $otag;
        $this->ctag = $ctag;

        return $closeIndex + strlen($close) - 1;
    }

    /**
     * Test whether it's time to change tags.
     *
     * @param string $tag Current tag name
     * @param string $text Mustache template source
     * @param int $index Current tokenizer index
     * @param int $tagLength Length of the opening/closing tag string
     *
     * @return boolean True if this is a closing section tag
     */
    protected function tagChange($tag, $text, $index, $tagLength)
    {
        return substr($text, $index, $tagLength) === $tag;
    }

}

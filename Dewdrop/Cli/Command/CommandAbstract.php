<?php

namespace Dewdrop\Cli\Command;

use Dewdrop\Cli\Run;
use Dewdrop\Cli\Renderer\RendererInterface;
use Dewdrop\Exception;

/**
 * The abstract class used by all CLI commands.
 *
 * This abstract class supplies CLI commands with argument parsing, help
 * content display, alias support, etc.
 *
 * @package Dewdrop
 */
abstract class CommandAbstract
{
    /**
     * Command argument is required.
     *
     * @const
     */
    const ARG_REQUIRED = true;

    /**
     * Command argument is optional.
     *
     * @const
     */
    const ARG_OPTIONAL = false;

    /**
     * @var \Dewdrop\Cli\Run
     */
    protected $runner;

    /**
     * The renderer that should be used for all command output.  No output
     * should be rendered directly (i.e. with echo, print, printf, etc.),
     * so that it is easier to capture and examine output during testing.
     *
     * @var \Dewdrop\Cli\Renderer\RendererInterface
     */
    protected $renderer;

    /**
     * The command name that should be used on the CLI to select this
     * command class for execution.  For example, when running the
     * dewdrop CLI tool like this:
     *
     * ./dewdrop command-name
     *
     * \Dewdrop\Cli\Run will select the command class that has a command
     * class that has a $command property value of "command-name"
     *
     * @var string
     */
    private $command;

    /**
     * A brief, 8-12 word description of this command's purpose.  This will
     * be displayed in the command's own help content and the global list
     * of available commands.
     *
     * @var string
     */
    private $description;

    /**
     * Any aliases that can be used to trigger this command in addition to
     * the primary command name.
     *
     * @var array
     */
    private $aliases = array();

    /**
     * The name of the command's primary argument.  The primary argument's
     * value can be specified without naming the argument explicitly on the
     * command line.  Using popular version control system Subversion as an
     * example, you can do a code checkout without specifying the name of the
     * path argument like this:
     *
     * svn checkout http://example.org/path
     *
     * In that case, the path argument is the primary argument of SVN's
     * checkout command.
     *
     * In Dewdrop, if your command had a primary argument of "name" and the
     * user supplied this input:
     *
     * ./dewdrop my-command --folder=example "Example Name Value"
     *
     * The argument parser would set the name argument's value to "Example Name
     * Value" because that is the value expression not explicitly assigned to
     * another argument name.
     *
     * Users can still explicitly set the argument name for the primary argument,
     * too, if they prefer:
     *
     * ./dewdrop --name="Example Name Value"
     *
     * @var string
     */
    private $primaryArg;

    /**
     * The arguments that are available for this command.
     *
     * @var array
     */
    private $args = array();

    /**
     * Examples of valid usage for this command.
     *
     * @var array
     */
    private $examples = array();

    /**
     * @param \Dewdrop\Cli\Run
     * @param \Dewdrop\Cli\Renderer\RendererInterface
     */
    public function __construct(Run $runner, RendererInterface $renderer)
    {
        $this->runner   = $runner;
        $this->renderer = $renderer;

        // All commands support the --help argument
        $this->addArg(
            'help',
            'Display the help message for this command',
            self::ARG_OPTIONAL
        );

        $this->init();

        if (!$this->command || !$this->description) {
            throw new Exception('You must set the name and description in your init() method.');
        }
    }

    /**
     * Implement the init() method in your command sub-class to set required
     * properties.  You'll likely call:
     *
     * - setCommand()
     * - setDescription()
     * - addAlias()
     * - addArg()
     * - addPrimaryArg()
     * - addExample()
     *
     * @return void
     */
    abstract public function init();

    /**
     * Run your command.  This will only be called if parseArgs() returns true,
     * indicating that the command line arguments could be successfully parsed
     * according the definitions you created in your init() method.
     */
    abstract public function execute();

    /**
     * Parse the arguments passed to this command.
     *
     * If the "--help" argument is present anywhere in the argument input, all
     * further parsing will be aborted and the command's help content will be
     * displayed.
     *
     * For all argument names and their aliases, there are multiple acceptable
     * formats of argument and value.  For example, all of these inputs are
     * equivalent:
     *
     * ./dewdrop my-command --argument-name=value
     * ./dewdrop my-command --argument-name value
     * ./dewdrop my-command --argument-alias=value
     * ./dewdrop my-command -argument-alias=value
     * ./dewdrop my-command -argument-alias value
     *
     * In short, you can use one or two dashes at the beginning of the argument
     * name and you can separate the value from the name with either a space
     * or an equals sign.
     *
     * For every argument your command supports, you need to implement a setter
     * method.  For example, if you have an argument with the name "my-argument"
     * then your command class needs a method called "setMyArgument()".
     *
     * Also note that the command API supports the concept of a "primary
     * argument".  See the documentation for the $primaryArgument property for
     * more information about that feature.
     *
     * @param array $input
     *
     * @return boolean Whether args were fully parsed and command can be executed.
     */
    public function parseArgs($input)
    {
        // Which args have been set while parsing
        $argsSet = array();

        foreach ($input as $index => $segment) {
            // In this loop, we're only interested in input indicated an argument name
            if (0 !== strpos($segment, '-')) {
                continue;
            }

            // If we encounter the --help argument, display command help and stop parsing
            if (0 === stripos($segment, '--help')) {
                $this->help();
                return false;
            }

            // Replace any "-" character at beginning of input
            $segment = preg_replace('/^-+/', '', $segment);

            if (false !== strpos($segment, '=')) {
                // If there's an equal sign present, our name and value are readily available
                list($name, $value) = explode('=', $segment);

                unset($input[$index]);
            } else {
                // Otherwise, we need to look to the next input segment for the value
                $name  = $segment;
                $next  = $index + 1;

                // The next input segment is only the value if it doesn't start with "-"
                if (isset($input[$next]) && !preg_match('/^-/', $input[$next])) {
                    $value = $input[$next];
                } else {
                    $this->abort('No value given for argument "' . $name . '"');
                    return false;
                }

                unset($input[$index]);
                unset($input[$next]);
            }

            $name     = strtolower($name);
            $selected = false;

            // Now that name and value are available, find matching arg from command definition
            foreach ($this->args as $arg) {
                if ($arg['name'] === $name) {
                    $selected = true;
                }

                foreach ($arg['aliases'] as $alias) {
                    if ($alias === $name) {
                        $selected = true;
                    }
                }

                if ($selected) {
                    $this->setArgValue($arg['name'], $value);

                    $argsSet[] = $arg['name'];
                    break;
                }
            }

            if (!$selected) {
                $this->abort('Attempting to set unknown argument "' . $name . '"');
                return false;
            }
        }

        // If after matching named args, there is one bit of input left, assign it to our primary arg
        if ($this->primaryArg && !in_array($this->primaryArg, $argsSet) && 1 === count($input)) {
            $this->setArgValue($this->primaryArg, current($input));

            $argsSet[] = $this->primaryArg;
            $input     = array();
        }

        // Ensure no required args were missed
        foreach ($this->args as $arg) {
            if ($arg['required'] && !in_array($arg['name'], $argsSet)) {
                $this->abort('Required argument "' . $arg['name'] . '" not set.');
                return false;
            }
        }

        return true;
    }

    /**
     * @var string $description
     * @return \Dewdrop\Cli\Command\CommandAbstract
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * This method is available so the Help command can display a list of
     * available commands.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $command
     * @return \Dewdrop\Cli\Command\CommandAbstract
     */
    public function setCommand($command)
    {
        $this->command = strtolower($command);

        return $this;
    }

    /**
     * This method is available so the Help command can display a list of
     * available commands.
     *
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Register an alias for this command.  This can be useful to provide
     * the user other ways to execute the command.  For example, you might
     * provide a shortened version of the command name so that experienced
     * users can avoid typing the full name once their comfortable with
     * the command.
     *
     * @param string $alias
     * @return \Dewdrop\Cli\Command\CommandAbstract
     */
    public function addAlias($alias)
    {
        $this->aliases[] = strtolower($alias);

        return $this;
    }

    /**
     * Add an argument while also setting it as the primary arg for this
     * command.  For more information about the primary arg feature, read
     * the docs on the $primaryArg property.
     *
     * @param string $name
     * @param string $description
     * @param boolean $required
     * @param array $aliases
     *
     * @return \Dewdrop\Cli\Command\CommandAbstract
     */
    public function addPrimaryArg($name, $description, $required, $aliases = array())
    {
        $this->primaryArg = $name;

        $this->addArg($name, $description, $required, $aliases);

        return $this;
    }

    /**
     * @param string $name
     * @param string $description
     * @param boolean $required
     * @param array $aliases
     *
     * @return \Dewdrop\Cli\Command\CommandAbstract
     */
    public function addArg($name, $description, $required, $aliases = array())
    {
        $this->args[] = array(
            'name'        => strtolower($name),
            'required'    => $required,
            'description' => $description,
            'aliases'     => array_map('strtolower', $aliases)
        );

        return $this;
    }

    /**
     * Add an example usage for this command.  These are displayed in the
     * command's help content.
     *
     * @param string $description
     * @param string $command
     *
     * @return \Dewdrop\Cli\Command\CommandAbstract
     */
    public function addExample($description, $command)
    {
        $this->examples[] = array(
            'description' => $description,
            'command'     => $command
        );

        return $this;
    }

    /**
     * Based on the supplied input command, determine whether this command
     * should be selected for argument parsing and execution.
     *
     * @param string $inputCommand
     *
     * @return \Dewdrop\Cli\Command\CommandAbstract
     */
    public function isSelected($inputCommand)
    {
        $inputCommand = strtolower($inputCommand);

        if ($inputCommand === $this->command) {
            return true;
        }

        foreach ($this->aliases as $alias) {
            if ($alias === $inputCommand) {
                return true;
            }
        }

        return false;
    }

    /**
     * Display help content for this command.
     *
     * The basic command name and description, any avaialble aliases, and
     * any avaialble examples are all included in the help display.
     *
     * This content can be accessed by called "--help" on this command
     * directly:
     *
     * ./dewdrop my-command --help
     *
     * Or, you can use the built-in help command to access it:
     *
     * ./dewdrop help my-command
     *
     * @return void
     */
    public function help()
    {
        $this->renderer
            ->title($this->getCommand())
            ->text($this->getDescription());

        if (count($this->aliases)) {
            $this->renderer->text('Aliases: ' . implode(', ', $this->aliases));
        }

        $this->renderer->newline();

        if (count($this->examples)) {
            $this->renderer->subhead('Examples');

            foreach ($this->examples as $example) {
                $this->renderer
                    ->text(rtrim($example['description'], ':') . ':')
                    ->text('    ' . $example['command'])
                    ->newline();
            }
        }

        if (count($this->args)) {
            $this->renderer->subhead('Arguments');

            $rows = array();

            foreach ($this->args as $arg) {
                $title = '--' . $arg['name'];

                $rows[$title] = sprintf(
                    '%s (%s)',
                    $arg['description'],
                    ($arg['required'] ? 'Required' : 'Optional')
                );
            }

            $this->renderer->table($rows);
        }

        return $this;
    }

    /**
     * Render the provided error message and display the command's help content.
     *
     * @param string $errorMessage
     *
     * @return \Dewdrop\Cli\Command\CommandAbstract
     */
    protected function abort($errorMessage)
    {
        $this->renderer->error($errorMessage);
        $this->help();
        return $this;
    }

    /**
     * Use the built-in passthru() function to call an external command and
     * return its exit status.  This is separated into its own method primarily
     * to make it easier to mock during testing.
     *
     * @param string $command
     * @return integer
     */
    protected function passthru($command)
    {
        passthru($command, $exitStatus);

        return $exitStatus;
    }

    /**
     * Change "~" prefix to the user's home folder.
     *
     * Bash doesn't do "~" evaluation automatically for command arguments, so
     * we do it here to avoid confusing developers by creating a "~" folder
     * in their WP install instead.
     *
     * @param string $path
     * @return string
     */
    protected function evalPathArgument($path)
    {
        if (0 === strpos($path, '~') && isset($_SERVER['HOME'])) {
            $path = $_SERVER['HOME'] . substr($path, 1);
        }

        return $path;
    }

    /**
     * Attempt to locate the named executable using "which", if it is
     * available.  Otherwise, just return the name and hope it is in
     * the user's $PATH.
     *
     * @param string $name
     * @return string
     */
    protected function autoDetectExecutable($name)
    {
        if (!file_exists('/usr/bin/which')) {
            return $name;
        } else {
            return trim(shell_exec("which {$name}")) ?: $name;
        }
    }

    /**
     * Set the valid of the specified argument.
     *
     * The argument's name is inflected to form a setter name that will be
     * called to set the value.  If no setter is available, execution will
     * be aborted.
     *
     * @param string $name
     * @param string $value
     *
     * @return \Dewdrop\Cli\Command\CommandAbstract
     */
    private function setArgValue($name, $value)
    {
        $words  = explode('-', $name);
        $setter = 'set' . implode('', array_map('ucfirst', $words));

        if (!method_exists($this, $setter)) {
            $this->abort('No setter method available for argument "' . $name . '"');
            return;
        }

        $this->$setter($value);

        return $this;
    }
}
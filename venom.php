<?php
class Venom_Application
{
    private $repos = array();

    public static function run()
    {
        $app = new self;
        try {
            $app->loadVenomfile();
            $app->download();
        } catch (Exception $e) {
            echo get_class($e) . ": {$e->getMessage()}", PHP_EOL;
        }
    }

    public function loadVenomfile()
    {
        if (file_exists('./Venomfile')) {
            $GLOBALS['venom'] = $this;
            include './Venomfile';
        } else {
            throw new RuntimeException('Venomfile not found');
        }
    }

    public function github($repo, $options = array())
    {
        $this->repos[] = new Venom_Repository_Github($repo, $options);
    }

    public function download()
    {
        foreach ($this->repos as $repo) {
            $this->cmd('wget', '-O', $repo->getTarGzFilename(), $repo->getTarGzUrl());
            $this->cmd('tar', 'xvzf', $repo->getTarGzFilename());
        }
    }

    private function cmd()
    {
        $cmd = join(' ', array_map('escapeshellarg', func_get_args()));
        echo $cmd, PHP_EOL;
        echo `$cmd`;
    }
}

interface Venom_RepositoryInterface
{
    function getTarGzFilename();
    function getTarGzUrl();
}

class Venom_Repository_Github implements Venom_RepositoryInterface
{
    private $url;

    private $user;

    private $project;

    private $branch;

    private $tag;

    public function __construct($url, $options = array())
    {
        $this->setUrl($url);
        $this->tag = isset($options['tag']) ? $options['tag'] : NULL;
        $this->branch = isset($options['branch']) ? $options['branch'] : NULL;
    }

    public function setUrl($url)
    {
        if (preg_match('#^https://github.com/([^/]+)/([^/]+)#', $url, $matches)) {
            $this->user    = $matches[1];
            $this->project = $matches[2];
        } else {
            throw new RuntimeException('Invalid GitHub URL specified');
        }
    }

    public function getTarGzFilename()
    {
        return "{$this->project}.tar.gz";
    }

    public function getTarGzUrl()
    {
        if (isset($this->tag)) {
            return "https://github.com/{$this->user}/{$this->project}/tarball/{$this->tag}";
        } else if (isset($this->branch)) {
            return "https://github.com/{$this->user}/{$this->project}/tarball/{$this->branch}";
        } else {
            return "https://github.com/{$this->user}/{$this->project}/tarball/master";
        }
    }
}

Venom_Application::run();

<?php

namespace Irs\Jbot\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Split extends Command
{
    const ARGUMENT_INPUT = 'input_file';
    const OPTION_TEMPLATE = 'template';
    const OPTION_EMPLOYEES = 'employees';
    const OPTION_PAGE_PER_FILE = 'page-per-file';

    public function configure()
    {
        $this->setName('split')
            ->setDescription('Splits PDF file with invoices to one file per employee')
            ->addArgument(self::ARGUMENT_INPUT, InputArgument::REQUIRED, 'Input PDF file')
            ->addOption(self::OPTION_TEMPLATE, 't', InputArgument::OPTIONAL, 'File name template', '{name} - {date}.pdf')
            ->addOption(self::OPTION_EMPLOYEES, 'e', InputArgument::OPTIONAL, 'Employees list. Tab separated file: 1st column is name, 2nd is email.', 'employees.txt')
            ->addOption(self::OPTION_PAGE_PER_FILE, 'p', InputArgument::OPTIONAL, 'Pages per output file', 2);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $list = $this->loadList($input->getOption(self::OPTION_EMPLOYEES), $input->getOption(self::OPTION_TEMPLATE));

        $this->splitPdf(
            $input->getArgument(self::ARGUMENT_INPUT),
            $list,
            $input->getOption(self::OPTION_PAGE_PER_FILE),
            $output
        );
    }

    protected function loadList($fileName, $template) : array
    {
        if (!file_exists($fileName)) {
            throw new \InvalidArgumentException("Unable to open $fileName");
        }
        $list = [];
        $fd = fopen($fileName, 'r');
        $i = 1;

        while ($item = fgetcsv($fd, 0,"\t")) {
            if (count($item) != 2) {
                throw new \InvalidArgumentException("Invalid input file; line $i should contain name and email");
            }
            list ($email, $name) = $item;

            $params = [
                'name' => $name,
                'date' => date('M y'),
            ];
            $list[$email] = $this->filter($template, $params);
            $i++;
        }
        fclose($fd);

        return $list;
    }

    protected function filter($template, array $args)
    {
        $placeholders = array_map(function ($v) {return '{' . $v . '}';}, array_keys($args));

        return str_replace($placeholders, $args, $template);
    }

    protected function splitPdf($from, array $to, $pagesPerFile, OutputInterface $output)
    {
        $fromPdf = new \FPDI;
        $pageCount = $fromPdf->setSourceFile($from);

        if ($pageCount / $pagesPerFile != count($to)) {
            throw new \InvalidArgumentException(sprintf(
                    'Employees list does not correspond to input file; it contains %d items but input file contains %d pages but need %d pages per file',
                    count($to), $pageCount, $pagesPerFile
                )
            );
        }
        $i = 1;

        foreach ($to as $toFilename) {
            $toPdf = new \FPDI;

            for ($j = 0; $j < $pagesPerFile; $j++, $i++) {
                $toPdf->AddPage();
                $toPdf->setSourceFile($from);
                $toPdf->useTemplate($toPdf->importPage($i));
            };
            $toPdf->Output($toFilename, 'F');
            $output->writeln("$toFilename created.");
        }
    }
}

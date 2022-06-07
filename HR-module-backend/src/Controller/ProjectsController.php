<?php

namespace App\Controller;

use App\Dto\AnswearDTO;
use App\Entity\Projects;
use App\Repository\ProjectsRepository;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Redmine\Client\NativeCurlClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Redmine\Exception;

#[Route('/api/v1')]
class ProjectsController extends AbstractController
{
    public NativeCurlClient $client;

    public function __construct()
    {
        $this->client = new NativeCurlClient("http://192.168.0.16:3000", "0fb32ce4b235496fb8cb386cd6e7a0dfefa2e2df");
    }

    #[Route('/projects', name: 'app_projects', methods: "GET")]
    public function index(ProjectsRepository $projectsRepository, EntityManagerInterface $entityManager): Response
    {
        $projects = $this->client->getApi('project')->all();
        foreach ($projects['projects'] as $project) {
            if (!$projectsRepository->find($project['id'])) {
                $projectHR = new Projects();
            }
            else {
                $projectHR = $projectsRepository->find($project['id']);
            }
            $projectHR->setId($project['id']);
            $projectHR->setDescription($project['description']);
            $projectHR->setName($project['name']);
            $projectHR->setStatus($project['status']);
            $timeStart = mb_substr($project['created_on'],0,10);
            $timeStart = DateTime::createFromFormat("Y-m-d", $timeStart);
            $projectHR->setCreatedOn($timeStart);
            $entityManager->persist($projectHR);
            $entityManager->flush();
        }
        $answer = new AnswearDTO();
        $answer->status = 'Sync';
        $answer->messageAnswear = "Sync " . $projects['total'];
        return $this->json($answer, Response::HTTP_OK);
    }
}

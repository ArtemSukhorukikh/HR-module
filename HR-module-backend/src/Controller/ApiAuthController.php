<?php

namespace App\Controller;

use App\Dto\AnswearDTO;
use App\Dto\Transformer\Request\UserRequestDTOTransformer;
use App\Dto\UserAuthDto;
use App\Dto\UserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerBuilder;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Annotations as OA;
use Nelmio\ApiDocBundle\Annotation\Security as SecurityOA;

#[Route('/api/v1')]
class ApiAuthController extends AbstractController
{
    private $serializer;
    private $validator;
    private $passwordHasher;
    private $userTransformer;

    public function __construct(UserRequestDTOTransformer $userRequestDTOTransformer, ValidatorInterface $validator, UserPasswordHasherInterface $passwordHasher)
    {
        $this->serializer = SerializerBuilder::create()->build();
        $this->validator = $validator;
        $this->passwordHasher = $passwordHasher;
        $this->userTransformer = $userRequestDTOTransformer;
    }

    /**
     * @OA\Get(
     *     path="api/v1/users/current",
     *     description="Get current user",
     * )
     * @OA\Response(
     *     response=200,
     *     description="Returns token user",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="token",
     *          type="string",
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response=400,
     *     description="Incorrect login and password pair",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="Error message",
     *          type="string"
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="default",
     *     description="Unxepected error",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="code",
     *          type="string"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string"
     *        ),
     *     )
     * )
     */
    #[Route('/auth', name: 'api_login', methods: ['POST'])]
    public function login()
    {
        //auth
    }
    /**
     * @OA\Get(
     *     path="api/v1/users/current",
     *     description="Get current user",
     * )
     * @OA\Response(
     *     response=200,
     *     description="Returns token user",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="token",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="roles",
     *          type="array",
     *          @OA\Items(type="string")
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response=400,
     *     description="",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="Error message",
     *          type="string"
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="default",
     *     description="Unxepected error",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="code",
     *          type="string"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string"
     *        ),
     *     )
     * )
     * @SecurityOA(name="Bearer")
     */
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        JWTTokenManagerInterface $JWTTokenManager
    ): Response
    {
        $userDto = $this->serializer->deserialize($request->getContent(), UserDto::class, 'json');
        $errors = $this->validator->validate($userDto);
        if ($userRepository->findOneBy(['username' => $userDto->username])) {
            $errors->add(new ConstraintViolation(
                message: 'User ' . $userDto->username .  ' already exists.',
                messageTemplate: 'User {{ value }} already exists.',
                parameters: ['value' => $userDto->username],
                root: $userDto,
                propertyPath: 'username',
                invalidValue: $userDto->username
            ));
        }
        if (count($errors) > 0) {
            $jsonErrors = [];
            foreach ($errors as $error) {
                $jsonErrors[$error->getPropertyPath()][] = $error->getMessage();
            }
            return $this->json([
                'errors' => $jsonErrors,
            ], Response::HTTP_BAD_REQUEST);
        }
        $user = new User;
        $user = $this->userTransformer->transformToObject($userDto);
        $user->setPassword($this->passwordHasher->hashPassword($user, $userDto->password));
        $entityManager->persist($user);
        $entityManager->flush();
        $userAuth = new UserAuthDto();
        $userAuth->roles =  $user->getRoles();
        $userAuth->token = $JWTTokenManager->create($user);
        return $this->json($userAuth, Response::HTTP_CREATED);
    }

    #[Route('/user/update', name: 'api_user_update', methods: ['PUT', 'POST'])]
    public function update(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        JWTTokenManagerInterface $JWTTokenManager
    ): Response
    {
        $userDto = $this->serializer->deserialize($request->getContent(), UserDto::class, 'json');
        $errors = $this->validator->validate($userDto);
        if ($userRepository->findOneBy(['username' => $userDto->username])) {
            if (count($errors) > 0) {
                $jsonErrors = [];
                foreach ($errors as $error) {
                    $jsonErrors[$error->getPropertyPath()][] = $error->getMessage();
                }
                return $this->json([
                    'errors' => $jsonErrors,
                ], Response::HTTP_BAD_REQUEST);
            }
            $user = $userRepository->findOneBy(['username' => $userDto->username]);
            $userChange = $this->userTransformer->transformToObject($userDto);
            $user->setUsername($userChange->getUsername());
            $user->setDateOfHiring($userChange->getDateOfHiring());
            $user->setFirstName($userChange->getFirstName());
            $user->setLastName($userChange->getLastName());
            $user->setPatronymic($userChange->getPatronymic());
            $user->setPosition($userChange->getPosition());
            $entityManager->persist($user);
            $entityManager->flush();
            $answer = new AnswearDTO();
            $answer->status = "Change";
            $answer->messageAnswear = "User Info change";
            return $this->json($answer, Response::HTTP_CREATED);
        }
        else {
            $errors->add(new ConstraintViolation(
                message: 'User ' . $userDto->username .  ' does not exists.',
                messageTemplate: 'User {{ value }} already exists.',
                parameters: ['value' => $userDto->username],
                root: $userDto,
                propertyPath: 'username',
                invalidValue: $userDto->username
            ));
        }

    }
}

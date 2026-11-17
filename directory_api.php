<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$department_groups = [
    "Information & Communication Technology" => [
        "Computer Engineering", "Electronic Engineering", "Electrical Engineering", 
        "Software Engineering", "Telecommunication Engineering", "Computer Science", "Information Technology"
    ],
    "Engineering & Architecture" => [
        "Civil Engineering", "Petroleum and Gas Engineering", "Mechanical Engineering", 
        "Textile Engineering", "Fashion & Textile Design", "Chemical Engineering", 
        "Mining Engineering", "Geological Engineering", "Architecture"
    ],
    "Basic Sciences" => [
        "Chemistry", "Mathematics", "Physics"
    ],
    "Life Sciences & Informatics" => [
        "Biotechnology", "Environmental Science", "Microbiology"
    ],
    "Management Sciences" => [
        "Management Sciences", "Economics", "Public Administration"
    ],
    "Social Sciences & Humanities" => [
        "Education", "English", "Fine Arts", "International Relations", 
        "Mass Communication", "Psychology", "Sociology"
    ],
    "Campus services" => [
        "Facilities", "IT", "Plumbing", "Electrical", "Hostel"
    ]
];

$campus_locations = [
    "Takatu Campus — Airport Road, Baleli, Quetta, Balochistan",
    "City Campus — Jinnah Town, Samungli Road, Quetta, Balochistan",
    "Chiltan Campus (Research) — Hazarganji, Quetta, Balochistan",
    "Muslim Bagh Campus — Muslim Bagh, Balochistan",
    "Zhob Campus — Zhob, Balochistan"
];

echo json_encode([
    "department_groups" => $department_groups,
    "campus_locations" => $campus_locations
], JSON_UNESCAPED_UNICODE);
?>
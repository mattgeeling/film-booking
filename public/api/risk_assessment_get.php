<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$bookingId = (int) ($_GET['booking_id'] ?? 0);
if ($bookingId <= 0) {
    json_error('Missing or invalid booking_id');
}

$pdo = db();

$bookingStmt = $pdo->prepare(
    'SELECT b.*, c.name AS client_name
     FROM bookings b LEFT JOIN clients c ON c.id = b.client_id
     WHERE b.id = ?'
);
$bookingStmt->execute([$bookingId]);
$booking = $bookingStmt->fetch();
if (!$booking) {
    json_error('Booking not found', 404);
}

$raStmt = $pdo->prepare('SELECT * FROM risk_assessments WHERE booking_id = ?');
$raStmt->execute([$bookingId]);
$ra = $raStmt->fetch();

$callSheetStmt = $pdo->prepare('SELECT location_contact_name, location_contact_phone, nearest_ae FROM call_sheets WHERE booking_id = ?');
$callSheetStmt->execute([$bookingId]);
$callSheet = $callSheetStmt->fetch();

function ra_default_standard_arrangements(): array
{
    return [
        'fire_detection' => ['answer' => 'Yes', 'detail' => 'Location manager to provide details of smoke detectors and alarms'],
        'fire_exits' => ['answer' => 'Yes', 'detail' => 'Location manager to advise crew of fire exits, ensure they are unlocked and communicate these along with the fire assembly points.'],
        'fire_extinguishers' => ['answer' => 'Yes', 'detail' => ''],
        'fire_briefing' => ['answer' => 'Yes', 'detail' => 'Location manager to conduct tool box talk on evacuation process on arrival. For filming at businesses emergency protocol will be in place, fire exits marked, and extinguishers available. Evacuate and wait safe distance outside the building. Call 999 immediately.'],
        'first_aiders' => ['answer' => 'N/A', 'detail' => 'Paramedic not deemed required for this filming. First aid kit carried with crew. First aider in crew.'],
        'heating_ventilation' => ['answer' => 'Yes', 'detail' => 'Crew working indoors in a heated/ventilated private space.'],
        'drinking_water' => ['answer' => 'Yes', 'detail' => 'Crew to carry refillable bottles.'],
        'washing_changing' => ['answer' => 'Yes', 'detail' => 'Toilets available on location.'],
    ];
}

function ra_default_hazards(): array
{
    return [
        [
            'hazard' => 'ASSAULT BY PERSON',
            'to_whom' => 'LOCATION CREW',
            'level' => '1',
            'precautions' => "All contributors are background checked during the casting period and have not found anything to raise any flags towards anti-social behaviour\nCrew to be buddied up to reduce risk from lone workers, where possible\nKeep in contact with production office and check in after filming.\nCrew do not have cash float\nKeep valuables/kit out of sight and equipment stored carefully\nValuables/kit not to be left unattended at any time and not be left in vehicles overnight.\nValuables/kit to be left in safe locked location (studio).\nCrew will not engage in negative or aggressive conversations with the general public and will avoid aggressive situations on the road.\nEnsure any threats or changes to situation are communicated across team at earliest opportunity and acknowledged.\nIf any crew feel uncomfortable then all must leave immediately.",
        ],
        [
            'hazard' => 'FILMING IN LOCATION',
            'to_whom' => 'LOCATION CREW',
            'level' => '2',
            'precautions' => "All equipment is to be supervised at all times and must not block walkways or fire exits.\nAll cables to be suitably ramped, taped or flown.\nCables should also be run alongside walls if possible.",
        ],
        [
            'hazard' => 'DRIVING – OFF-CAMERA VEHICLE IMPACT/COLLISION',
            'to_whom' => 'ALL',
            'level' => '3',
            'precautions' => "Only competent, confident drivers will be asked to drive.\nDrivers licence to be submitted to Production prior to shoot and checked for restrictions, codes, conditions/diseases which may affect driving.\nOnly insured crew will drive vehicles\nSpeed limits and rules of the road must be adhered to. Seat belts worn\nBefore each departure factor in time for standard vehicle checks – fuel, oil, water, lights, hazards, wing mirrors, windscreens.\nBefore departure check weather forecast\nNo mobile phones when driving, no eating or drinking while driving or other distractions like loud music.\nDo not drive under influence of alcohol/drugs or when taking medication that makes you feel drowsy.\nDriving in dark/bad weather increases risk of accident and lowers braking reaction time.\nEnsure all kit is properly secured in boot or footwell.\nIf it's felt a member of the team is driving unsafely, this must be reported to PM\nIf any driver does not feel safe to drive, they MUST not drive and should report to PM\nCrew have been advised in case of an accident – call 999 if in need of medical attention. Call PM as soon as possible once safe/being looked after. Swap contact/insurance details. Call production to follow up. Do not admit any blame.",
        ],
        [
            'hazard' => 'PHYSICAL FATIGUE',
            'to_whom' => 'LOCATION CREW, CONTRIBUTORS',
            'level' => '2',
            'precautions' => "All filming will be filmed in one location, to limit time spent in transit and potentially increasing hours of shooting day.\nWorking day is carefully scheduled on a scene-by-scene basis at the planning stage to reduce chance of schedule overrun.\nOne-hour breaks for lunch and rest breaks during day will be factored in\nSchedule minimum of 11 hours break between shoot days\nDriving to be shared between crew members\nAll crew/contributors have completed Fitness to Participate/Work and Covid-19 declaration forms prior to employment to alert production confidentially to any underlying health conditions\nClear lines for team to refer up to Director for any concerns on physical wellbeing\nCrew to flag to Director any concerns or changes in physical well-being and/or tiredness before, during or after the shoot.",
        ],
        [
            'hazard' => 'FIRE',
            'to_whom' => 'LOCATION CREW',
            'level' => '2',
            'precautions' => "For filming at businesses: member of staff to give briefing of fire protocol including fire evacuation procedure, fire exits, fire extinguishers etc on crew arrival\nIn the event of any fire – sound the alarm and/or call 999 and evacuate.\nDo not use lifts in event of a fire.\nDo not stop to take personal possessions.\nKeep close to the floor in smoky conditions",
        ],
        [
            'hazard' => 'ELECTRIC SHOCK',
            'to_whom' => 'CAST, CREW',
            'level' => '2',
            'precautions' => "Equipment will be kept to a minimum for each designated scene\nEquipment should be stored securely away from cast/business members still on premises\nIf needing to re-charge - check with property owners/location representative if plugging in equipment and undertake a visual check of any sockets prior to plugging in\nAll equipment is to be tested prior to checking out for location – either by technician or facilities house or freelancer depending on owner of equipment\nEquipment must be annually PAT tested to assure electrical safety\nAny technical issues or broken kit to be taken out of circulation and reported to PM immediately.\nVisually inspect equipment prior to use – frayed cables, bad connections etc\nDo not overload charging points, or 'daisy chain' adapters\nExcess electrical cable should not be left coiled as it can lead to overheating\nAll personal electrical equipment, including chargers, must be appropriately safety-rated.\nCrew will inspect all lithium batteries before and throughout the shoot.\nAny signs of leaking or expansion will be reported, and the batteries taken out of circulation.\nCrew to take additional care with lithium batteries. Be aware that overcharging or dropping/damaging them may result in the batteries bursting into flames.",
        ],
        [
            'hazard' => 'FOOD HYGIENE',
            'to_whom' => 'LOCATION CREW',
            'level' => '2',
            'precautions' => "Drink bottles to be name labelled on location to ensure crew do not drink from same bottle\nLunch should be eaten in vehicle or at location to limit need to go into restaurants.\nAlcohol should not be consumed by crew on location or during the working day\nIf eating food from takeaways request disposable cutlery\nFor food purchased from shops or takeaways, packaging should be wiped over with anti-bac wipes on purchase/collection.\nCrew will bring pre-prepared food and drinks to location where possible.\nLocal reputable companies will be used for ordering lunches/dinners.\nAll food waste including packaging to be disposed of into a closed top bin and removed from location, ensuring the location is checked for any rubbish before leaving.",
        ],
        [
            'hazard' => 'MANUAL HANDLING',
            'to_whom' => 'LOCATION CREW',
            'level' => '2',
            'precautions' => "Production will ensure parking is as close to location as possible to avoid having to carry equipment far\nCrew will ensure manual handling can be shared so one member is not carrying all the kit every day\nManual handling avoided where possible. Only trained, competent people to carry out manual handling tasks.\nAll loads to be assessed before lifting.\nFit-for-purpose handling aids provided where necessary and suitable.\nSuitable footwear to be worn.\nNo one with pre-existing back injuries to undertake such tasks or if pregnant.\nRoutes to be checked for obstructions and lighting before task commences.\nCrew to be aware of any personal musculoskeletal injuries/disease. Only lift within your capabilities.\nOnly lift within individual capability. Think before lifting – where will it be placed? Keep the load close to the waist. Adopt a stable position. Get a good hold. Keep the back straight, use the leg muscles, keep elbows tucked in.",
        ],
        [
            'hazard' => 'SLIPS, TRIPS, FALLS / TRIP HAZARD / ACCESS / EGRESS',
            'to_whom' => 'LOCATION CREW, CAST',
            'level' => '3',
            'precautions' => "Light stands to be weighted with sandbags.\nLights to be positioned away from flammable material.\nLighting heads will be left to cool before handling/wrapping. Suitable protective gloves worn if handling hot lighting heads.\nStands positioned to ensure they are not blocking exits, doorways, top of stairs or anywhere else likely to pose a trip hazard\nConsolidate kit and store neatly packed out of the way when not in use\nTape cables down to avoid potential trip hazards using gaffer tape\nDo not block access/egress, footpaths, main corridor, hallways or fire exits\nBe alert! Pay due care and attention at all times\nTake extra care going up and down external and internal stairs to locations. Hold onto handrails. Take your time and don't rush\nCrew to wear sensible flat footwear with grips\nMop up any spills immediately. Only drinks with lids to be consumed on location\nEnsure suitable emergency RV point is arranged outside the building and communicated to each team member.",
        ],
        [
            'hazard' => 'DATA PROTECTION / PRIVACY',
            'to_whom' => 'LOCATION CREW',
            'level' => '2',
            'precautions' => "Call sheets are to be marked as confidential documents\nCall sheets and other production documents should only be printed where necessary\nLocation team briefed not to leave any call sheets/paperwork on location\nAny data files carried by location crew should be stored on encrypted memory sticks\nAll details of telephone, emails, home addresses should be kept confidential",
        ],
    ];
}

$standardArrangements = ($ra && $ra['standard_arrangements']) ? json_decode($ra['standard_arrangements'], true) : ra_default_standard_arrangements();
$hazards = ($ra && $ra['hazards']) ? json_decode($ra['hazards'], true) : ra_default_hazards();

$locationContact = $ra['location_contact'] ?? '';
if ($locationContact === '' && $callSheet) {
    $locationContact = trim(($callSheet['location_contact_name'] ?? '') . ($callSheet['location_contact_phone'] ? ' — ' . $callSheet['location_contact_phone'] : ''));
}
$nearestAe = $ra['nearest_ae'] ?? '';
if ($nearestAe === '' && $callSheet) {
    $nearestAe = $callSheet['nearest_ae'] ?? '';
}

json_ok([
    'booking' => [
        'id' => (int) $booking['id'],
        'title' => $booking['title'],
        'location' => $booking['location'],
        'what3words' => $booking['what3words'],
        'client_name' => $booking['client_name'],
        'start_datetime' => $booking['start_datetime'],
    ],
    'client_name' => $ra['client_name'] ?? ($booking['client_name'] ?? ''),
    'location_contact' => $locationContact,
    'director_name' => $ra['director_name'] ?? '',
    'director_email' => $ra['director_email'] ?? '',
    'director_mobile' => $ra['director_mobile'] ?? '',
    'production_manager_name' => $ra['production_manager_name'] ?? '',
    'production_manager_email' => $ra['production_manager_email'] ?? '',
    'production_manager_mobile' => $ra['production_manager_mobile'] ?? '',
    'brief_description' => $ra['brief_description'] ?? '',
    'crew_experts' => $ra['crew_experts'] ?? '',
    'nearest_ae' => $nearestAe,
    'standard_arrangements' => $standardArrangements,
    'hazards' => $hazards,
    'signoff_director_name' => $ra['signoff_director_name'] ?? '',
    'signoff_director_date' => $ra['signoff_director_date'] ?? '',
    'signoff_producer_name' => $ra['signoff_producer_name'] ?? '',
    'signoff_producer_date' => $ra['signoff_producer_date'] ?? '',
    'saved' => (bool) $ra,
]);

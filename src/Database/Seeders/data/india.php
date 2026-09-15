<?php

declare(strict_types=1);

/**
 * India geographic dataset: country + all states / union territories
 * with their district-headquarters and major cities.
 *
 * This returns:
 *   [
 *       'country' => ['name' => 'India', 'short_name' => 'IN'],
 *       'states'  => [
 *           'Maharashtra' => ['code' => 'MH', 'cities' => ['Mumbai', ...]],
 *           ...
 *       ],
 *   ]
 */
return [
    'country' => [
        'name'       => 'India',
        'short_name' => 'IN',
    ],

    'states' => [
        'Andhra Pradesh' => [
            'code' => 'AP',
            'cities' => [
                'Amaravati', 'Visakhapatnam', 'Vijayawada', 'Guntur', 'Nellore',
                'Tirupati', 'Kakinada', 'Rajahmundry', 'Eluru', 'Ongole', 'Kadapa',
                'Kurnool', 'Anantapur', 'Chittoor', 'Srikakulam', 'Vizianagaram',
                'Nandyal', 'Machilipatnam', 'Proddatur', 'Tadepalligudem', 'Adoni',
                'Hindupur', 'Bhimavaram', 'Guntakal', 'Tenali', 'Madanapalle',
                'Narasaraopet', 'Markapur', 'Amalapuram', 'Palamaner', 'Rayachoti',
                'Kavali', 'Dharmavaram', 'Vijayawada', 'Sullurpeta', 'Chilakaluripet',
                'Kandukuru', 'Naidupeta', 'Gudivada', 'Nandigama', 'Sattenapalle',
                'Tadpatri', 'Puttur', 'Nandyal', 'Yemmiganur', 'Atmakur', 'Gudur',
                'Nuzvid', 'Tanuku', 'Vadlamuru', 'Cheepurupalle', 'Palasa', 'Tekkali',
            ],
        ],
        'Arunachal Pradesh' => [
            'code' => 'AR',
            'cities' => [
                'Itanagar', 'Tawang', 'Bomdila', 'Along', 'Pasighat', 'Tezu',
                'Khonsa', 'Yingkiong', 'Anini', 'Roing', 'Ziro', 'Daporijo',
                'Seppa', 'Namsai', 'Changlang', 'Basar', 'Palin', 'Naharlagun',
                'Koloriang', 'Deomali', 'Jairampur', 'Ruksin', 'Manmaw',
            ],
        ],
        'Assam' => [
            'code' => 'AS',
            'cities' => [
                'Guwahati', 'Dispur', 'Silchar', 'Dibrugarh', 'Jorhat', 'Nagaon',
                'Tezpur', 'Bongaigaon', 'Tinsukia', 'Sivasagar', 'Golaghat',
                'Dhubri', 'Nalbari', 'Barpeta', 'Karimganj', 'Hailakandi',
                'Kokrajhar', 'Goalpara', 'North Lakhimpur', 'Diphu', 'Haflong',
                'Mangaldoi', 'Mariani', 'Lumding', 'Rangia', 'Mankachar', 'Sibsagar',
                'Morigaon', 'Jagiroad', 'Dhemaji', 'Bilasipara', 'Barpathar',
            ],
        ],
        'Bihar' => [
            'code' => 'BR',
            'cities' => [
                'Patna', 'Gaya', 'Bhagalpur', 'Muzaffarpur', 'Darbhanga', 'Purnia',
                'Arrah', 'Bettiah', 'Motihari', 'Sasaram', 'Begusarai', 'Katihar',
                'Saharsa', 'Madhubani', 'Buxar', 'Chhapra', 'Siwan', 'Gopalganj',
                'Munger', 'Sitamarhi', 'Hajipur', 'Vaishali', 'Samastipur',
                'Aurangabad', 'Rohtas', 'Dehri', 'Nawada', 'Jamui', 'Kishanganj',
                'Forbesganj', 'Araria', 'Supaul', 'Lakhisarai', 'Sheikhpura',
                'Bhabhua', 'Jehanabad', 'Arwal', 'Narkatiyaganj', 'Bagaha',
                'Rosera', 'Madhepura', 'Banmankhi', 'Bihta', 'Digha',
            ],
        ],
        'Chhattisgarh' => [
            'code' => 'CG',
            'cities' => [
                'Raipur', 'Bilaspur', 'Bhilai', 'Korba', 'Durg', 'Rajnandgaon',
                'Jagdalpur', 'Raigarh', 'Ambikapur', 'Dhamtari', 'Mahasamund',
                'Kanker', 'Bemetara', 'Kavardha', 'Mungeli', 'Balodabazar',
                'Balod', 'Gariaband', 'Kondagaon', 'Narayanpur', 'Bijapur',
                'Dantewada', 'Sukma', 'Khairagarh', 'Sakti', 'Janjgir',
                'Champa', 'Kurud', 'Rajnagar', 'Pandariya',
            ],
        ],
        'Goa' => [
            'code' => 'GA',
            'cities' => [
                'Panaji', 'Margao', 'Vasco da Gama', 'Mapusa', 'Ponda', 'Bicholim',
                'Curchorem', 'Sanquelim', 'Cuncolim', 'Quepem', 'Canacona',
                'Pernem', 'Aldona', 'Calangute', 'Benaulim', 'Cavelossim',
            ],
        ],
    'Gujarat' => [
            'code' => 'GJ',
            'cities' => [
                'Ahmedabad', 'Surat', 'Vadodara', 'Rajkot', 'Bhavnagar', 'Jamnagar',
                'Gandhinagar', 'Anand', 'Nadiad', 'Mehsana', 'Bharuch', 'Junagadh',
                'Gandhidham', 'Porbandar', 'Navsari', 'Valsad', 'Morbi', 'Surendranagar',
                'Patan', 'Amreli', 'Bhuj', 'Palanpur', 'Godhra', 'Dahod', 'Vapi',
                'Kalol', 'Himatnagar', 'Modasa', 'Veraval', 'Botad', 'Bhachau',
                'Ankleshwar', 'Bardoli', 'Dhoraji', 'Jetpur', 'Keshod', 'Kadi',
                'Visnagar', 'Deesa', 'Sidhpur', 'Udhana', 'Jasdan', 'Savarkundla',
                'Wadhwan', 'Dhrangadhra', 'Limbdi', 'Mansa', 'Vijapur', 'Becharaji',
            ],
        ],
        'Haryana' => [
            'code' => 'HR',
            'cities' => [
                'Chandigarh', 'Faridabad', 'Gurugram', 'Panipat', 'Ambala', 'Hisar',
                'Rohtak', 'Karnal', 'Sonipat', 'Panchkula', 'Yamunanagar', 'Bhiwani',
                'Sirsa', 'Jind', 'Kaithal', 'Rewari', 'Palwal', 'Mahendragarh',
                'Nuh', 'Fatehabad', 'Jhajjar', 'Kurukshetra', 'Gohana', 'Bahadurgarh',
                'Tohana', 'Dabwali', 'Safidon', 'Assandh', 'Charkhi Dadri', 'Narnaul',
                'Hodal', 'Kathura', 'Pehowa', 'Thanesar', 'Hansi', 'Ellenabad',
                'Narwana', 'Rania', 'Taraori', 'Sarsa', 'Barwala', 'Uklana Mandi',
            ],
        ],
        'Himachal Pradesh' => [
            'code' => 'HP',
            'cities' => [
                'Shimla', 'Dharamshala', 'Mandi', 'Solan', 'Hamirpur', 'Palampur',
                'Kullu', 'Manali', 'Kangra', 'Una', 'Bilaspur', 'Chamba', 'Nahan',
                'Sundarnagar', 'Baddi', 'Paonta Sahib', 'Jogindernagar', 'Rohru',
                'Rampur Bushahr', 'Keylong', 'Reckong Peo', 'Kasauli', 'Arki',
                'Kinnaur', 'Kotkhai', 'Nurpur', 'Dehra Gopipur', 'Karsog', 'Karsog',
            ],
        ],
        'Jharkhand' => [
            'code' => 'JH',
            'cities' => [
                'Ranchi', 'Jamshedpur', 'Dhanbad', 'Bokaro', 'Hazaribagh', 'Deoghar',
                'Giridih', 'Ramgarh', 'Dumka', 'Phusro', 'Chatra', 'Gumla', 'Lohardaga',
                'Pakur', 'Sahibganj', 'Godda', 'Garhwa', 'Latehar', 'Palamu',
                'Simdega', 'Khunti', 'West Singhbhum', 'East Singhbhum', 'Koderma',
                'Jamtara', 'Jharia', 'Chirkunda', 'Madhupur', 'Medininagar',
                'Chatra', 'Daltonganj', 'Basarh', 'Gobindpur', 'Saraikela',
            ],
        ],
        'Karnataka' => [
            'code' => 'KA',
            'cities' => [
                'Bengaluru', 'Mysuru', 'Hubballi', 'Mangaluru', 'Belagavi',
                'Kalaburagi', 'Dharwad', 'Ballari', 'Vijayapura', 'Shivamogga',
                'Tumakuru', 'Davangere', 'Raichur', 'Bidar', 'Hassan', 'Udupi',
                'Mandya', 'Kolar', 'Chikkamagaluru', 'Hospet', 'Gadag', 'Bagalkot',
                'Chitradurga', 'Ramanagara', 'Chikkaballapura', 'Karwar',
                'Chamarajanagar', 'Haveri', 'Koppal', 'Kodagu', 'Madikeri',
                'Belthangady', 'Bhadravati', 'Ranebennur', 'Sirsi', 'Sindhanur',
                'Yadgir', 'Gangavati', 'Jamkhandi', 'Athani', 'Sira', 'Pavagada',
                'Kollegal', 'Malavalli', 'Nanjangud', 'Tiptur', 'Hunsur', 'Sakleshpur',
                'Kunigal', 'Gubbi', 'Challakere', 'Harihar', 'Shikaripura',
            ],
        ],
        'Kerala' => [
            'code' => 'KL',
            'cities' => [
                'Thiruvananthapuram', 'Kochi', 'Kozhikode', 'Thrissur', 'Kollam',
                'Alappuzha', 'Palakkad', 'Kannur', 'Kottayam', 'Malappuram',
                'Kasaragod', 'Idukki', 'Wayanad', 'Pathanamthitta', 'Ernakulam',
                'Punalur', 'Aluva', 'Thodupuzha', 'Chalakudy', 'Perumbavoor',
                'Muvattupuzha', 'Kalamassery', 'Attingal', 'Nedumangad', 'Varkala',
                'Guruvayur', 'Ottapalam', 'Mannarkkad', 'Chittur', 'Kanjikuzhi',
                'Payyanur', 'Taliparamba', 'Thalassery', 'Mattannur', 'Kalpetta',
                'Sulthan Bathery', 'Mananthavady', 'Kattappana', 'Neyyattinkara',
            ],
        ],
    'Madhya Pradesh' => [
            'code' => 'MP',
            'cities' => [
                'Bhopal', 'Indore', 'Jabalpur', 'Gwalior', 'Ujjain', 'Sagar',
                'Rewa', 'Satna', 'Ratlam', 'Burhanpur', 'Khandwa', 'Khargone',
                'Mandsaur', 'Chhindwara', 'Betul', 'Vidisha', 'Hoshangabad',
                'Sehore', 'Damoh', 'Shivpuri', 'Guna', 'Morena', 'Bhind',
                'Chhatarpur', 'Tikamgarh', 'Panna', 'Dewas', 'Neemuch', 'Rajgarh',
                'Balaghat', 'Seoni', 'Katni', 'Singrauli', 'Shahdol', 'Umaria',
                'Dindori', 'Mandla', 'Anuppur', 'Sidhi', 'Sheopur', 'Datia',
                'Alirajpur', 'Jhabua', 'Barwani', 'Harda', 'Narsinghpur',
                'Gadarwara', 'Malthone', 'Pachore', 'Raghogarh',
            ],
        ],
        'Maharashtra' => [
            'code' => 'MH',
            'cities' => [
                'Mumbai', 'Pune', 'Nagpur', 'Nashik', 'Aurangabad', 'Thane',
                'Solapur', 'Kolhapur', 'Navi Mumbai', 'Amravati', 'Sangli',
                'Malegaon', 'Akola', 'Latur', 'Dhule', 'Ahmednagar', 'Chandrapur',
                'Parbhani', 'Ichalkaranji', 'Jalgaon', 'Bhusawal', 'Nanded',
                'Wardha', 'Beed', 'Osmanabad', 'Satara', 'Ratnagiri', 'Yavatmal',
                'Gondia', 'Bhandara', 'Washim', 'Hingoli', 'Buldhana', 'Gadchiroli',
                'Raigad', 'Panvel', 'Vasai-Virar', 'Kalyan-Dombivli', 'Mira-Bhayandar',
                'Palghar', 'Sindhudurg', 'Baramati', 'Karad', 'Phaltan',
                'Shirdi', 'Nashik Road', 'Kopargaon', 'Raver', 'Malkapur',
            ],
        ],
        'Manipur' => [
            'code' => 'MN',
            'cities' => [
                'Imphal', 'Bishnupur', 'Churachandpur', 'Ukhrul', 'Thoubal',
                'Senapati', 'Chandel', 'Tamenglong', 'Jiribam', 'Kakching',
                'Lilong', 'Mayang Imphal', 'Moirang', 'Nambol', 'Wangjing',
                'Andro', 'Kumbi', 'Sugnu', 'Kwakta',
            ],
        ],
        'Meghalaya' => [
            'code' => 'ML',
            'cities' => [
                'Shillong', 'Tura', 'Jowai', 'Nongstoin', 'Baghmara', 'Williamnagar',
                'Nongpoh', 'Ampati', 'Mairang', 'Resubelpara', 'Rongjeng',
                'Cherrapunji', 'Sohra', 'Dawki', 'Umiam', 'Ribhoi', 'Khliehriat',
            ],
        ],
        'Mizoram' => [
            'code' => 'MZ',
            'cities' => [
                'Aizawl', 'Lunglei', 'Champhai', 'Saiha', 'Serchhip', 'Kolasib',
                'Lawngtlai', 'Mamit', 'Saitual', 'Khawzawl', 'Hnahthial', 'Zawlnuam',
                'Thenzawl', 'Darlawn', 'Vairengte', 'Biate', 'Siaha', 'Kolosib',
            ],
        ],
    'Nagaland' => [
            'code' => 'NL',
            'cities' => [
                'Kohima', 'Dimapur', 'Mokokchung', 'Wokha', 'Zunheboto', 'Tuensang',
                'Mon', 'Phek', 'Longleng', 'Kiphire', 'Peren', 'Chumukedima',
                'Pfutsero', 'Meluri', 'Tamlu', 'Tseminyu', 'Noklak', 'Aghunaqa',
            ],
        ],
        'Odisha' => [
            'code' => 'OD',
            'cities' => [
                'Bhubaneswar', 'Cuttack', 'Rourkela', 'Sambalpur', 'Puri', 'Berhampur',
                'Balasore', 'Baripada', 'Bhadrak', 'Jharsuguda', 'Angul', 'Kendrapara',
                'Jagatsinghpur', 'Puri', 'Koraput', 'Rayagada', 'Nabarangpur',
                'Malkangiri', 'Jajpur', 'Keonjhar', 'Mayurbhanj', 'Ganjam',
                'Khordha', 'Nayagarh', 'Puri', 'Gajapati', 'Nuapada', 'Balangir',
                'Subarnapur', 'Bargarh', 'Deogarh', 'Dhenkanal', 'Kandhamal',
                'Boudh', 'Kalahandi', 'Sundargarh', 'Chatrapur', 'Rajgangpur',
                'Bhuban', 'Athagarh', 'Paralakhemundi', 'Titlagarh', 'Kantabanji',
            ],
        ],
        'Punjab' => [
            'code' => 'PB',
            'cities' => [
                'Chandigarh', 'Ludhiana', 'Amritsar', 'Jalandhar', 'Patiala',
                'Bathinda', 'Mohali', 'Hoshiarpur', 'Batala', 'Pathankot', 'Moga',
                'Firozpur', 'Sangrur', 'Barnala', 'Fazilka', 'Kharar', 'Mansa',
                'Nawanshahr', 'Kapurthala', 'Faridkot', 'Gurdaspur', 'Ropar',
                'Tarn Taran', 'Fatehgarh Sahib', 'Muktsar', 'Abohar', 'Rajpura',
                'Khanna', 'Phagwara', 'Malerkotla', 'Sunam', 'Dera Bassi',
                'Zirakpur', 'Dhuri', 'Nabha', 'Sultanpur Lodhi', 'Khadoor Sahib',
                'Samana', 'Dirba', 'Lehra', 'Budhlada', 'Bajwa',
            ],
        ],
        'Rajasthan' => [
            'code' => 'RJ',
            'cities' => [
                'Jaipur', 'Jodhpur', 'Udaipur', 'Kota', 'Bikaner', 'Ajmer',
                'Bhilwara', 'Alwar', 'Bharatpur', 'Sikar', 'Pali', 'Sri Ganganagar',
                'Hanumangarh', 'Tonk', 'Kishangarh', 'Beawar', 'Sawai Madhopur',
                'Chittorgarh', 'Pratapgarh', 'Churu', 'Jhunjhunu', 'Nagaur',
                'Barmer', 'Jalore', 'Sirohi', 'Banswara', 'Dungarpur', 'Baran',
                'Jhalawar', 'Dausa', 'Karauli', 'Sawai Madhopur', 'Balotra',
                'Fatehpur', 'Neem Ka Thana', 'Phalodi', 'Pilani', 'Sujangarh',
                'Ratangarh', 'Sardarshahar', 'Gangapur City', 'Hindaun', 'Bundi',
                'Dholpur', 'Raniwara', 'Sumerpur', 'Rajsamand', 'Bali',
            ],
        ],
        'Sikkim' => [
            'code' => 'SK',
            'cities' => [
                'Gangtok', 'Namchi', 'Gyalshing', 'Mangan', 'Rangpo', 'Jorethang',
                'Singtam', 'Rhenock', 'Rabong', 'Chungthang', 'Rumtek', 'Pakyong',
                'Yuksom', 'Lachung', 'Lachen', 'Pelling', 'Soreng', 'Dentam',
            ],
        ],
        'Tamil Nadu' => [
            'code' => 'TN',
            'cities' => [
                'Chennai', 'Coimbatore', 'Madurai', 'Tiruchirappalli', 'Salem',
                'Tirunelveli', 'Vellore', 'Erode', 'Thoothukudi', 'Tiruppur',
                'Dindigul', 'Nagercoil', 'Cuddalore', 'Kanchipuram', 'Kumbakonam',
                'Thanjavur', 'Karur', 'Neyveli', 'Ooty', 'Sivakasi', 'Avadi',
                'Tambaram', 'Ambattur', 'Pudukkottai', 'Rajapalayam', 'Karur',
                'Ramanathapuram', 'Sivaganga', 'Thenkasi', 'Teni', 'Virudhunagar',
                'Krishnagiri', 'Dharmapuri', 'Namakkal', 'Viluppuram', 'Tiruvannamalai',
                'Ariyalur', 'Perambalur', 'Nagapattinam', 'Mayiladuthurai',
                'Mylapore', 'Besant Nagar', 'Anna Nagar', 'T Nagar', 'Tambaram',
                'Chromepet', 'Porur', 'Palavakkam', 'Marina Beach', 'Kovalam',
            ],
        ],
    'Telangana' => [
            'code' => 'TS',
            'cities' => [
                'Hyderabad', 'Warangal', 'Nizamabad', 'Karimnagar', 'Khammam',
                'Ramagundam', 'Mahbubnagar', 'Nalgonda', 'Adilabad', 'Suryapet',
                'Miryalaguda', 'Siddipet', 'Jagtial', 'Mancherial', 'Bhadrachalam',
                'Medak', 'Sangareddy', 'Kothagudem', 'Kamareddy', 'Vikarabad',
                'Tandur', 'Wanaparthy', 'Gadwal', 'Jogulamba', 'Nirmal', 'Asifabad',
                'Bhongir', 'Zaheerabad', 'Jangaon', 'Mahbubabad', 'Peddapalli',
                'Palvancha', 'Kodad', 'Kodangal', 'Armoor', 'Koratla',
            ],
        ],
        'Tripura' => [
            'code' => 'TR',
            'cities' => [
                'Agartala', 'Dharmanagar', 'Udaipur', 'Kailashahar', 'Belonia',
                'Khowai', 'Ambassa', 'Amarpur', 'Bishalgarh', 'Melaghar',
                'Sonamura', 'Sabroom', 'Binora', 'Teliamura', 'Kumarghat',
                'Santirbazar', 'Radhakishorepur', 'Matarbari',
            ],
        ],
        'Uttar Pradesh' => [
            'code' => 'UP',
            'cities' => [
                'Lucknow', 'Kanpur', 'Varanasi', 'Agra', 'Prayagraj', 'Meerut',
                'Ghaziabad', 'Noida', 'Bareilly', 'Aligarh', 'Moradabad',
                'Gorakhpur', 'Jhansi', 'Saharanpur', 'Faizabad', 'Etawah',
                'Mathura', 'Vrindavan', 'Ayodhya', 'Rampur', 'Shahjahanpur',
                'Firozabad', 'Budaun', 'Hapur', 'Bulandshahr', 'Muzaffarnagar',
                'Bijnor', 'Amroha', 'Sambhal', 'Mainpuri', 'Farrukhabad',
                'Hardoi', 'Unnao', 'Raebareli', 'Sitapur', 'Bahraich',
                'Lakhimpur Kheri', 'Banda', 'Chitrakoot', 'Hamirpur', 'Mahoba',
                'Jalaun', 'Lalitpur', 'Orai', 'Etah', 'Kasganj', 'Fatehpur',
                'Pratapgarh', 'Kaushambi', 'Sultanpur', 'Amethi', 'Basti',
                'Siddharthnagar', 'Sant Kabir Nagar', 'Azamgarh', 'Ballia',
                'Ghazipur', 'Chandauli', 'Mirzapur', 'Sonbhadra', 'Mau',
                'Deoria', 'Kushinagar', 'Maharajganj', 'Gonda', 'Balrampur',
                'Shravasti', 'Pilibhit', 'Kanpur Dehat', 'Auraiya', 'Kannauj',
                'Barabanki', 'Jaunpur', 'Ambedkar Nagar',
            ],
        ],
        'Uttarakhand' => [
            'code' => 'UK',
            'cities' => [
                'Dehradun', 'Haridwar', 'Nainital', 'Roorkee', 'Haldwani',
                'Rishikesh', 'Kotdwar', 'Kashipur', 'Rudrapur', 'Almora',
                'Pithoragarh', 'Pauri', 'Champawat', 'Rudraprayag', 'Bageshwar',
                'Mussoorie', 'Vikasnagar', 'Rampur', 'Tanakpur', 'Sitarganj',
                'Jaspur', 'Gadarpur', 'Bazpur', 'Doiwala', 'Chamba', 'Tehri',
                'Srinagar', 'Uttarkashi', 'Chamoli Gopeshwar', 'Karnaprayag',
                'New Tehri', 'Bhowali', 'Bhikiyasain', 'Dwarahat', 'Ranikhet',
            ],
        ],
    'West Bengal' => [
            'code' => 'WB',
            'cities' => [
                'Kolkata', 'Howrah', 'Darjeeling', 'Siliguri', 'Asansol',
                'Durgapur', 'Bardhaman', 'Malda', 'Kharagpur', 'Haldia',
                'Krishnanagar', 'Berhampore', 'Jalpaiguri', 'Cooch Behar',
                'Alipore', 'Uluberia', 'Bangur', 'Balurghat', 'Suri',
                'Birbhum', 'Purulia', 'Bankura', 'Midnapore', 'Kanthi',
                'Tamluk', 'Raiganj', 'Islampur', 'Naihati', 'Bhatpara',
                'Baranagar', 'Barasat', 'Bidhannagar', 'Dum Dum', 'Barrackpore',
                'Basirhat', 'Halishahar', 'Santipur', 'Chandannagar', 'Medinipur',
                'Bishnupur', 'Kurseong', 'Kalimpong', 'Ratua', 'Bhagabangola',
                'Domkal', 'Katwa', 'Kalna', 'Ranaghat', 'Bangoon',
            ],
        ],
    'Andaman and Nicobar Islands' => [
            'code' => 'AN',
            'cities' => [
                'Port Blair', 'Car Nicobar', 'Diglipur', 'Mayabunder', 'Rangat',
                'Hut Bay', 'Campbell Bay', 'Neil Island', 'Havelock Island',
                'Wandoor', 'Bambooflat', 'Shaheed Dweep', 'Swaraj Dweep',
                'Kadamtala', 'Nancowry', 'Garacharma', 'Prothrapur',
            ],
        ],
        'Chandigarh' => [
            'code' => 'CH',
            'cities' => [
                'Chandigarh', 'Sector 17', 'Sector 22', 'Sector 26', 'Manimajra',
                'Sector 10', 'Sector 11', 'Naya Gaon', 'Kaimbwala', 'Maloya',
                'Daria',
            ],
        ],
        'Dadra and Nagar Haveli and Daman and Diu' => [
            'code' => 'DN',
            'cities' => [
                'Daman', 'Diu', 'Silvassa', 'Amli', 'Naroli', 'Kachigam',
                'Chhaya', 'Dungra', 'Kadaiya', 'Ghoghala', 'Sayli', 'Dabhel',
                'Varkund', 'Bordi',
            ],
        ],
        'Delhi' => [
            'code' => 'DL',
            'cities' => [
                'New Delhi', 'Shahdara', 'Rohini', 'Dwarka', 'Saket', 'Lajpat Nagar',
                'Karol Bagh', 'Chandni Chowk', 'Connaught Place', 'Narela',
                'Najafgarh', 'Vasant Kunj', 'Hauz Khas', 'Janakpuri', 'Pitampura',
                'Kashmere Gate', 'Okhla', 'Mayur Vihar', 'Preet Vihar', 'Patparganj',
                'Mehrauli', 'R K Puram', 'Sarita Vihar', 'Pushp Vihar', 'Anand Vihar',
                'Laxmi Nagar', 'Karkardooma', 'Gandhi Nagar', 'Krishna Nagar',
                'Seemapuri', 'Yamuna Vihar', 'Gokalpur', 'Mukherjee Nagar',
                'Civil Lines', 'Rajouri Garden', 'Subhash Nagar', 'Lodhi Colony',
            ],
        ],
    'Jammu and Kashmir' => [
            'code' => 'JK',
            'cities' => [
                'Srinagar', 'Jammu', 'Anantnag', 'Baramulla', 'Udhampur',
                'Kathua', 'Sopore', 'Pulwama', 'Kulgam', 'Shopian', 'Budgam',
                'Ganderbal', 'Bandipora', 'Kupwara', 'Rajouri', 'Poonch',
                'Doda', 'Ramban', 'Kishtwar', 'Samba', 'Reasi',
                'Handwara', 'Pattan', 'Bijbehara', 'Awantipora', 'Traal',
                'Charar-e-Sharief', 'Khrew', 'Magam', 'Kokernag',
            ],
        ],
        'Ladakh' => [
            'code' => 'LA',
            'cities' => [
                'Leh', 'Kargil', 'Khaltsi', 'Diskit', 'Nubra', 'Padum',
                'Changthang', 'Zanskar', 'Sankoo', 'Drass', 'Mulbekh', 'Chumathang',
                'Spituk', 'Thiksey', 'Hemis', 'Tangtse', 'Nimo', 'Saspol',
            ],
        ],
        'Lakshadweep' => [
            'code' => 'LD',
            'cities' => [
                'Kavaratti', 'Agatti', 'Andrott', 'Amini', 'Kalpeni', 'Kadmat',
                'Minicoy', 'Chethlat', 'Bitra', 'Kiltan', 'Bangaram', 'Suheli',
                'Cheriyam', 'Agalatti', 'Melthen',
            ],
        ],
        'Puducherry' => [
            'code' => 'PY',
            'cities' => [
                'Puducherry', 'Karaikal', 'Mahe', 'Yanam', 'Ozhukarai',
                'Villianur', 'Thirubuvanai', 'Mannadipet', 'Ariyankuppam',
                'Kottucherry', 'Bahour', 'Nedungadu', 'Thirunallar', 'Karaikal Medu',
                'Chandrapadi', 'Tirunallur', 'Palloor', 'Panthakkal',
            ],
        ],
    ],
];
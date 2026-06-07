<?php
declare(strict_types=1);

require_once __DIR__ . '/social.php';

function seed_demo_users(): array
{
    $female = ['Neha Sharma','Priya Verma','Anjali Mehta','Riya Kapoor','Sneha Joshi','Pooja Singh','Komal Gupta','Nisha Yadav','Simran Kaur','Aarti Mishra','Kavya Rao','Meera Nair','Isha Jain','Divya Patel','Tanya Arora','Sakshi Bansal','Muskan Khan','Nandini Das','Shreya Roy','Payal Soni','Sonam Chauhan','Rashmi Tiwari','Kirti Saxena','Juhi Malhotra','Mansi Agarwal','Bhavna Reddy','Garima Bose','Ayesha Ali','Ritika Sen','Preeti Rana','Saloni Dubey','Swati Kulkarni','Palak Desai','Monika Thakur','Ruchi Tripathi','Alisha Fernandes','Chhavi Pandey','Tanvi Bhat','Deepika Gill','Khushi Sinha','Mahima Kapoor','Aakanksha Rai','Shruti Menon','Radhika Shah','Vaishali Jha','Navya Iyer','Heena Qureshi','Trisha Dutta','Sana Sheikh','Yamini Goyal'];
    $male = ['Rahul Sharma','Amit Verma','Vikas Mehta','Rohit Kapoor','Sandeep Joshi','Kunal Singh','Mohit Gupta','Arjun Yadav','Deepak Kumar','Manish Mishra','Aditya Rao','Nikhil Nair','Varun Jain','Abhishek Patel','Rajat Arora','Gaurav Bansal','Sameer Khan','Akash Das','Shivam Roy','Tarun Soni','Harsh Chauhan','Ravi Tiwari','Pankaj Saxena','Yash Malhotra','Ankit Agarwal','Pranav Reddy','Sahil Bose','Faizan Ali','Ayush Sen','Naveen Rana','Mayank Dubey','Dev Kulkarni','Chirag Desai','Rakesh Thakur','Vivek Tripathi','Neil Fernandes','Laksh Pandey','Rohan Bhat','Jatin Gill','Hemant Sinha','Dhruv Kapoor','Ashish Rai','Siddharth Menon','Jay Shah','Vaibhav Jha','Krish Iyer','Imran Qureshi','Anirudh Dutta','Rehan Sheikh','Tushar Goyal'];
    $places = [
        ['Rajasthan','Jaipur',26.9124,75.7873],['Delhi','New Delhi',28.6139,77.2090],['Maharashtra','Mumbai',19.0760,72.8777],
        ['Karnataka','Bengaluru',12.9716,77.5946],['Uttar Pradesh','Lucknow',26.8467,80.9462],['West Bengal','Kolkata',22.5726,88.3639],
        ['Telangana','Hyderabad',17.3850,78.4867],['Gujarat','Ahmedabad',23.0225,72.5714],['Madhya Pradesh','Indore',22.7196,75.8577],
        ['Punjab','Chandigarh',30.7333,76.7794],['Tamil Nadu','Chennai',13.0827,80.2707],['Bihar','Patna',25.5941,85.1376]
    ];
    $interests = ['music,good conversations,travel','books,coffee,friendship','fitness,movies,food','technology,bike rides,music','photography,weekend trips,chai','cooking,art,respectful friendship','cricket,travel,meeting people','dance,movies,casual chat'];
    $templates = [
        'Hi, I am %s from %s. I enjoy %s and respectful conversations.',
        'Namaste, main %s hoon, %s se. Mujhe %s aur genuine friendship pasand hai.',
        'Hey! %s here from %s. Looking for good conversations around %s.',
        'Hello, main %s se hoon. %s meri favourite cheezein hain; friendly log welcome.',
        'Finding kind people in %s. I am %s and I like %s.',
        'Casual chat, positive vibes and %s. Main %s, %s se hoon.'
    ];
    $pdo = db();
    $created = 0;
    foreach ([['Female',$female],['Male',$male]] as [$gender,$names]) {
        foreach ($names as $i => $name) {
            $username = 'demo_' . strtolower($gender[0]) . '_' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
            $exists = $pdo->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
            $exists->execute([$username]);
            if ($exists->fetch()) continue;
            [$state,$city,$lat,$lng] = $places[$i % count($places)];
            $interest = $interests[$i % count($interests)];
            $bio = sprintf($templates[$i % count($templates)], $name, $city, $interest);
            $days = 15 + (($i * 7 + ($gender === 'Female' ? 3 : 5)) % 350);
            $hours = 1 + (($i * 11) % 168);
            $createdAt = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            $activeAt = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO users (public_user_id,name,username,email,mobile,password,gender,state,city,latitude,longitude,is_demo_user,account_created_at,last_active_at,profile_completed,bio,interests,preferred_gender_filter,created_at,last_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([generate_public_user_id(),$name,$username,$username.'@demo.myself.local','9000'.str_pad((string)($gender === 'Female' ? $i : $i + 50),6,'0',STR_PAD_LEFT),password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),$gender,$state,$city,$lat,$lng,1,$createdAt,$activeAt,1,$bio,$interest,'Any',$createdAt,$activeAt]);
            $id = (int)$pdo->lastInsertId();
            $birthYear = date('Y') - (21 + ($i % 16));
            $pdo->prepare('INSERT INTO user_profiles (user_id,bio,city,gender,date_of_birth,age_verified) VALUES (?,?,?,?,?,1)')->execute([$id,$bio,$city,$gender,$birthYear . '-06-15']);
            $pdo->commit();
            $created++;
        }
    }
    return ['created' => $created, 'total_demo' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_demo_user=1')->fetchColumn()];
}


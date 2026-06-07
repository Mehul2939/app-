function sendWhatsApp() {
    var name = document.getElementById("userName").value;

    var message = `मैं ${name} एसोसिएशन में अपना 1 करोड़ 8 लाख रुपए इंश्योरेंस करवाना चाहता हु ! 
मैंने संपूर्ण होस हवास मैं एसोसिएशन के नियमों को पढ़ लिया है और मैं उनका पालन करूंगा 
और मे अटेंडेंस एप से मिलने वाली प्रतिदिन की राशि को एक साथ पांच जनवरी 2027 को लूंगा ! 
मेरा इंश्योरेंस करने की कृपा करे !`;

    var url = "https://wa.me/919928221039?text=" + encodeURIComponent(message);
    window.open(url, '_blank');
}

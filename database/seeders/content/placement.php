<?php

use Database\Seeders\Support\Q;

/** Placement bank: 6 objective items per CEFR level (PRD 3.4). */
return [
    'PRE_A1' => [
        Q::mcq('Hello! My ___ is Tom.', ['name', 'names', 'is', 'am'], 0),
        Q::mcq('How many? ✋ (five fingers)', ['four', 'five', 'six', 'nine'], 1),
        Q::mcq('I ___ a student.', ['is', 'are', 'am', 'be'], 2),
        Q::mcq('This is ___ apple.', ['a', 'an', 'the a', '—'], 1),
        Q::mcq('The sky is ___.', ['blue', 'chair', 'happy', 'eat'], 0),
        Q::mcq('one cat, two ___', ['cat', 'cats', 'cates', 'catss'], 1),
    ],
    'A1' => [
        Q::mcq('She ___ in a bank.', ['work', 'works', 'working', 'is work'], 1),
        Q::mcq('There ___ two chairs in the kitchen.', ['is', 'be', 'are', 'am'], 2),
        Q::mcq('Look! The children ___ football.', ['play', 'plays', 'are playing', 'played'], 2),
        Q::mcq('___ you like coffee?', ['Does', 'Do', 'Are', 'Is'], 1),
        Q::mcq('My birthday is ___ May.', ['on', 'at', 'in', 'to'], 2),
        Q::mcq('This is Anna. ___ brother is a doctor.', ['His', 'Her', 'Their', 'Its'], 1),
    ],
    'A2' => [
        Q::mcq('Yesterday I ___ to the cinema.', ['go', 'went', 'gone', 'going'], 1),
        Q::mcq('This book is ___ than that one.', ['interesting', 'more interesting', 'most interesting', 'interestinger'], 1),
        Q::mcq('Look at those clouds! It ___ rain.', ["'s going to", 'will be', 'is raining', 'rains'], 0),
        Q::mcq('How ___ water do you drink every day?', ['many', 'much', 'more', 'lot'], 1),
        Q::mcq('I was watching TV when the phone ___.', ['rang', 'was ringing', 'rings', 'has rung'], 0),
        Q::mcq('She ___ goes to the gym — maybe once a year.', ['always', 'usually', 'often', 'hardly ever'], 3),
    ],
    'B1' => [
        Q::mcq('I ___ in Jakarta since 2019.', ['live', 'am living', 'have lived', 'lived'], 2),
        Q::mcq('If it rains tomorrow, we ___ at home.', ['stay', 'will stay', 'would stay', 'stayed'], 1),
        Q::mcq('The bridge ___ in 1990.', ['built', 'was built', 'has built', 'is building'], 1),
        Q::mcq('You ___ wear a seatbelt. It is the law.', ['must', 'might', 'could', 'would'], 0),
        Q::mcq('She told me that she ___ tired.', ['is', 'was', 'be', 'were being'], 1),
        Q::mcq('I enjoy ___ in the mountains.', ['to hike', 'hiking', 'hike', 'hiked'], 1),
    ],
    'B2' => [
        Q::mcq('If I had known about the meeting, I ___ it.', ['would attend', 'will attend', 'would have attended', 'attended'], 2),
        Q::mcq('By the time we arrived, the film ___.', ['already started', 'had already started', 'has already started', 'was already starting'], 1),
        Q::mcq('She ___ have left already — her coat is gone.', ['must', 'should', 'can', 'need'], 0),
        Q::mcq('This time next week, I ___ on a beach in Bali.', ['will lie', 'will be lying', 'am lying', 'lie'], 1),
        Q::mcq('He asked me where ___.', ['did I live', 'I lived', 'do I live', 'I do live'], 1),
        Q::mcq('The new policy will ___ a big impact on small businesses.', ['make', 'do', 'have', 'take'], 2),
    ],
    'C1' => [
        Q::mcq('Not only ___ late, but he also forgot the documents.', ['he arrived', 'did he arrive', 'he did arrive', 'arrived he'], 1),
        Q::mcq('It was the manager ___ approved the changes.', ['which', 'who', 'whom', 'what'], 1),
        Q::mcq('The results seem to ___ that the treatment is effective.', ['suggest', 'say', 'tell', 'speak'], 0),
        Q::mcq('I would rather you ___ smoke in here.', ["don't", "didn't", "won't", "haven't"], 1),
        Q::mcq('Had I known, I ___ differently.', ['would act', 'would have acted', 'will have acted', 'acted'], 1),
        Q::mcq('The government is ___ to introduce new tax rules next year.', ['due', 'about', 'bound for', 'set for'], 0),
    ],
    'C2' => [
        Q::mcq('Little ___ that the decision would change everything.', ['they realised', 'did they realise', 'they did realise', 'realised they'], 1),
        Q::mcq('His argument, ___ persuasive, rests on a flawed premise.', ['however', 'albeit', 'whereas', 'despite'], 1),
        Q::mcq('The committee has yet to ___ a consensus.', ['arrive', 'reach', 'get', 'make up'], 1),
        Q::mcq('She was ___ by the news that she could barely speak.', ['so taken aback', 'such taken aback', 'too taken aback', 'very taken aback'], 0),
        Q::mcq('Which word is the most formal way to say "start"?', ['kick off', 'get going', 'commence', 'begin'], 2),
        Q::mcq('It is imperative that every applicant ___ the form in person.', ['submits', 'submit', 'submitted', 'will submit'], 1),
    ],
];

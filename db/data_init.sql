--              --- DATA INIT -----

USE price_monitoring_system;

-- data init for price platform (special cases)
INSERT INTO platforms (platform_name, platform_url, platform_url_merchant, platform_url_price, platform_status) VALUES 
('Price.com.hk', 'https://www.price.com.hk/', 'starshop.php?p=', 'product.php?p=', 'active');
-- ('WCSLMall', 'https://www.wcslmall.com/', 'inactive'),
-- ('CentralField', 'https://www.centralfield.com/', 'inactive'),
-- ('BUYMORE', 'https://www.buymore.hk/', 'inactive'),
-- ('Faroll', 'https://www.faroll.com/', 'inactive'),
-- ('Terminalhk', 'https://www.terminalhk.com/', 'inactive'),
-- ('ShopPEGASUS', 'https://www.shop.pegasus.com/', 'inactive'),
-- ('JUMBO-COMPUTER', 'https://www.jumbo-computer.com/', 'inactive'),
-- ('SE Computer', 'https://www.secomputer.com.hk/', 'inactive');

-- sample data (To be delete) --
/* Both products & merchant init should align with actual data */
INSERT INTO products (product_name, product_model, reference_price, min_acceptable_price, max_acceptable_price, product_description) VALUES
('QNAP 2-Bay NAS', 'TS-216-2G', 1300, 1299, 1399, 'TS-216-2G-Description'),
('QNAP 4-Bay NAS', 'TS-464C2-8G', 3299, 2999, 3399, 'TS-464C2-8G-Description');

INSERT INTO merchants (merchant_name, email, phone, address) VALUES
('Leader Peripheral', 'leaderpc@netvigator.com', '27087000', '香港九龍深水埗福華街146-152號,黃金商場地面33號舖'),
('Farm Computer 農場電腦', 'info@farmcomputer.com.hk', '97372236', '香港灣仔軒尼詩道130號灣仔電腦城1樓164-166號舖'),
('isolution', 'info@i-solution.hk', '21520313', '香港灣仔軒尼詩道130號電腦城1樓104號鋪'),
('荃豐 A3A', 'shoptwa3a@cosmictechnology.com.hk', '27201318', '新界荃灣西樓角路138-168號荃豐中心地庫A3A號舖'),
('Sam''s Technology Company', 'samchow323@gmail.com', '23742881', '九龍旺角奶路臣街8-8A旺角電腦中心, 3/F, 322-323舖'),
('Techspot 科點', 'hello@techspot.com.hk', '26518338', '香港中環皇后大道中33號萬邦行商場2樓210號舖'),
('Digital House 數碼屋', 'sales@digitalhouse.hk', '21773803', '香港九龍觀塘開源道68號觀塘廣場3樓325號舖'),
('Tech Easy', 'order@techeasy.com.hk ', '61898399', '香港中環皇后大道中33號萬邦行地下G樓G3A號舖'),
('Flash Computer 閃電腦', 'info@flashcomputer.com.hk', '37092410', '香港上環永樂街1-3號世瑛大廈地下3號鋪'),
('DIGITAL HOUSE W', '298@digitalhouse.hk', '63122175', '九龍觀塘開源道68號觀塘廣場3樓325號舖'),
('富豪電腦 Regal Computer', 'regal20a@gmail.com', '70722730', '灣仔軒尼詩道130號灣仔電腦城1樓125號舖'),
('Polytech 保利達電腦', 'polytech1304@gmail.com', '70731304', '旺角上海街426號萬事昌中心1501室'),
('Foresoon Computer 科訊電腦', 'csa@foresoon.com.hk', '63032308', '香港灣仔軒尼詩道130號,灣仔電腦城114-118,131-132,137-138號舖'),
('Green Mac', 'watermsshop@gmail.com', '51213434', '香港灣仔軒尼詩道130號灣仔電腦城2樓265-266號鋪'),
('I-Shop Limited 世紀網絡', 'info@ishop-hk.com', '31074040', '香港灣仔軒尼詩道130號灣仔電腦中心2樓247-248號舖'),
('Comdex 滙訊科技', 'info@comdex.com.hk', '22434899', '九龍深水埗福華街146-152號黃金電腦商場LG 37&41,39,57B&61,24,28號鋪'),
('Honest Technology Co.', 'info@honesttech.com.hk', '23729928', '九龍觀塘開源道68號觀塘廣場閣樓M28號舖'),
('未來科技', 'ASK@BUYMORE.HK', '21173773', '香港灣仔軒尼詩道298 號298電腦特區UG樓158-159號舖'),
('衍光電腦科技公司', 'sales@hinkwong.com', '39969952', '-'),
('南博電腦 Southtech computer', 'w02price@vdohk.com', '59830522', '香港灣仔軒尼詩道130號灣仔電腦城2樓252-253室'),
('G2 System', 'g2public.work@gmail.com', '23878955', '九龍深水埗元洲街85-95號新高登電腦商場2樓206-207鋪'),
('SE Computer Ltd', 'sales@secomputer.com.hk', '35863930', '九龍深水埗福華街146-152號黃金電腦商場地庫62號舖'),
('SimPro-Tech Technology', 'sales@simpro-tech.com', '31757704', '九龍觀塘開源道50號利寶時中心501'),
('創基電腦', 'c.base2008@yahoo.com.hk', '95035346', '九龍深水埗欽洲街高登電腦中心1樓48A號舖'),
('博略網絡科技', 'enquiry@gsnt.hk', '21561599', '九龍觀塘開源道55號開聯工業中心B座14樓07室'),
('暉煌電腦 FAI WONG COMPUTER', 'yiuhk@msn.com', '26600632', '新界大埔安慈路3號翠屏商場1樓21號鋪');

-- data init for existing merchants in platform
INSERT INTO platform_merchant_mappings (platform_id, merchant_id, platform_merchant_id, platform_merchant_name) VALUES
(1, 1, '2869', 'Leader Peripheral'),
(1, 2, '14926', 'Farm Computer 農場電腦'),
(1, 3, '4057', 'isolution'),
(1, 4, '14737', '荃豐 A3A'),
(1, 5, '3011', 'Sam''s Technology Company'),
(1, 6, '3317', 'Techspot 科點'),
(1, 7, '4126', 'Digital House 數碼屋'),
(1, 8, '6631', 'Tech Easy'),
(1, 9, '16722', 'Flash Computer 閃電腦'),
(1, 10, '14229', 'DIGITAL HOUSE W'),
(1, 11, '6623', '富豪電腦 Regal Computer'),
(1, 12, '16362', 'Polytech 保利達電腦'),
(1, 13, '748', 'Foresoon Computer 科訊電腦'),
(1, 14, '2191', 'Green Mac'),
(1, 15, '1247', 'I-Shop Limited 世紀網絡'),
(1, 16, '16961', 'Comdex 滙訊科技'),
(1, 17, '552', 'Honest Technology Co.'),
(1, 18, '654', '未來科技'),
(1, 19, '2608', '衍光電腦科技公司'),
(1, 20, '15905', '南博電腦 Southtech computer'),
(1, 21, '898', 'G2 System'),
(1, 22, '580', 'SE Computer Ltd'),
(1, 23, '1154', 'SimPro-Tech Technology'),
(1, 24, '965', '創基電腦'),
(1, 25, '1768', '博略網絡科技'),
(1, 26, '17517', '暉煌電腦 FAI WONG COMPUTER');

-- (2, 1, 'xxx', 'yyy')
-- (2, 2, 'xxx', 'yyy')
-- (2, 3, 'xxx', 'yyy')
-- (2, 4, 'xxx', 'yyy')
-- (2, 5, 'xxx', 'yyy')

-- data init for existing products in platform (ids)
INSERT INTO product_url_mappings (product_id, platform_id, platform_product_id) VALUES
(1, 1, '606102'),
(2, 1, '633588');



-- -- data init for users
-- INSERT INTO users (username, password, email, role) VALUES
-- ('admin', 'admin', 'admin@example.com', 'admin');